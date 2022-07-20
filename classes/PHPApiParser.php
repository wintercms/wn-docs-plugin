<?php namespace Winter\Docs\Classes;

use Markdown;
use phpDocumentor\Reflection\DocBlock\Tags\Author;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\Void_;
use PhpParser\Comment\Doc;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Error;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Glob;
use Winter\Storm\Support\Arr;

/**
 * PHP API Parser.
 *
 * This class will parse a directory for all PHP files and will extract the necessary information to generate
 * documentation for these files. This includes constants, properties, methods and events.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
class PHPApiParser
{
    /** Base path where the documentation is tored. */
    protected string $basePath;

    /** Source paths where files will be read, relative to the base path. */
    protected array $sourcePaths = [];

    /**
     * Ignore paths.
     *
     * This will be an array with the path as a key, and a glob regex as the value to test the filename against.
     */
    protected array $ignorePaths = [];

    /** List of file paths found */
    protected array $paths = [];

    /** List of namespaces encountered */
    protected array $namespaces = [];

    /** All classes scanned during parsing */
    protected array $classes = [];

    /** A list of paths that could not be parsed. */
    protected array $failedPaths = [];

    /**
     * A list of classes, with each class containing an array of events found. The details of these events will be
     * included in the class definitions.
     */
    protected array $events = [];

    /** Factory instance for generating DocBlock reflections */
    protected DocBlockFactory $docBlockFactory;

    /**
     * Constructor.
     */
    public function __construct(string $basePath, array|string $sourcePaths = [], array|string $ignorePaths = [])
    {
        $this->basePath = (substr($basePath, -1) !== '/') ? ($basePath . '/') : $basePath;
        $this->sourcePaths = (is_string($sourcePaths)) ? [$sourcePaths] : $sourcePaths;
        $ignorePaths = (is_string($ignorePaths)) ? [$ignorePaths] : $ignorePaths;

        if (count($ignorePaths)) {
            foreach ($ignorePaths as $ignorePath) {
                $this->ignorePaths[$ignorePath] = Glob::toRegex($this->basePath . ltrim($ignorePath, '/'));
            }
        }
    }

    /**
     * Parse the given base path for all PHP files and extract documentation information.
     */
    public function parse(): void
    {
        // Create parser and node finder
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder;

        // Add name resolver
        $nameResolver = new \PhpParser\NodeVisitor\NameResolver;
        $nodeTraverser = new \PhpParser\NodeTraverser;
        $nodeTraverser->addVisitor($nameResolver);

        foreach ($this->getPaths() as $file) {
            $contents = file_get_contents($file);

            // Parse PHP file
            try {
                $parsed = $parser->parse($contents);
                $parsed = $nodeTraverser->traverse($parsed);
            } catch (Error $error) {
                $this->failedPaths[] = [
                    'path' => $file,
                    'error' => $error->getMessage()
                ];
                continue;
            }

            // Ensure that we are dealing with a single class, trait or interface
            $objects = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Class_::class);
            $objects = array_merge($objects, $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Trait_::class));
            $objects = array_merge($objects, $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Interface_::class));

            if (!count($objects)) {
                $this->failedPaths[] = [
                    'path' => $file,
                    'error' => 'No object definition found.',
                ];
                continue;
            } elseif (count($objects) > 1) {
                $this->failedPaths[] = [
                    'path' => $file,
                    'error' => 'More than one object definition exists in this path.',
                ];
                continue;
            }

            // Find namespace
            $namespace = $nodeFinder->findFirstInstanceOf($parsed, \PhpParser\Node\Stmt\Namespace_::class);
            if (is_null($namespace)) {
                $namespace = '__GLOBAL__';
            } else {
                $namespace = (string) $namespace->name;
            }
            if (!in_array($namespace, $this->namespaces)) {
                $this->namespaces[] = $namespace;
            }

            // Find use cases
            $singleUses = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Use_::class);
            $groupedUses = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\GroupUse::class);
            $uses = $this->parseUseCases($singleUses, $groupedUses);

            // Find method calls that have comments
            $methodsWithEvents = $nodeFinder->find($parsed, function (\PhpParser\Node $node) {
                return $node instanceof \PhpParser\Node\Expr\MethodCall
                    && $node->getDocComment()
                    && $this->isEventDocBlock($node->getDocComment());
            });

            // Parse the objects
            switch (get_class($objects[0])) {
                case \PhpParser\Node\Stmt\Class_::class:
                    $class = $this->parseClassNode($objects[0], $namespace, $uses, $methodsWithEvents);
                    break;
                case \PhpParser\Node\Stmt\Trait_::class:
                    $class = $this->parseTraitNode($objects[0], $namespace, $uses, $methodsWithEvents);
                    break;
                case \PhpParser\Node\Stmt\Interface_::class:
                    $class = $this->parseInterfaceNode($objects[0], $namespace, $uses);
                    break;
            }

            $class['path'] = $file;
            $this->classes[$class['class']] = $class;
        }

        // Once we've parsed the classes, we need to determine inheritance
        $this->processInheritance();

        // Finally, add some contextualisation to certain aspects
        $this->processContext();
    }

    /**
     * List all paths found in the base paths.
     *
     * This method is cached after the first call.
     *
     * @param boolean $force Skip the cache.
     * @return array
     */
    public function getPaths(bool $force = false)
    {
        // Return cached paths
        if (count($this->paths) && !$force) {
            return $this->paths;
        }

        $paths = [];

        foreach ($this->sourcePaths as $sourcePath) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->basePath . ltrim($sourcePath, '/'))
            );

            foreach ($iterator as $path) {
                if ($path->isDir()) {
                    continue;
                }

                if (strtolower($path->getExtension()) !== 'php') {
                    continue;
                }

                if (count($this->ignorePaths)) {
                    foreach (array_values($this->ignorePaths) as $ignoreRegex) {
                        if (preg_match($ignoreRegex, $path->getPathname())) {
                            continue 2;
                        }
                    }
                }

                $paths[] = $path->getPathname();
            }
        }

        return $this->paths = $paths;
    }

    /**
     * Get all namespaces.
     *
     * This should be run after `parse()`.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Get all classes.
     *
     * This should be run after `parse()`. Depending on how large a codebase is parsed, this could be a MASSIVE array,
     * so it's recommended instead to get a class map and retrieve classes individually.
     *
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Gets a nested map of classes, based on namespace.
     *
     * This should be run after `parse()`.
     *
     * @return array
     */
    public function getClassMap()
    {
        $map = [];

        foreach ($this->getNamespaces() as $namespace) {
            $dotNamespace = str_replace('\\', '.', $namespace);
            Arr::set($map, $dotNamespace, []);
        }

        // Insert class names into map
        foreach (array_keys($this->classes) as $className) {
            $dotClassName = str_replace('\\', '.', $className);
            Arr::set($map, $dotClassName, $className);
        }

        $map = Arr::sortRecursive($map, SORT_STRING);

        return $map;
    }

    /**
     * Gets the details of a single class.
     *
     * This should be run after `parse()`. If the class does not exist in the parsed class list, `null` will be
     * returned.
     *
     * @return array|null
     */
    public function getClass(string $class)
    {
        if (!array_key_exists($class, $this->classes)) {
            return null;
        }

        return $this->classes[$class];
    }

    /**
     * Parses the "use" cases found in the PHP class.
     *
     * @param string $namespace
     * @param array $singleUses
     * @param array $groupedUses
     * @return array
     */
    protected function parseUseCases(array $singleUses = [], array $groupedUses = [])
    {
        $uses = [];

        // Process single uses
        if (count($singleUses)) {
            foreach ($singleUses as $use) {
                $name = (string) $use->uses[0]->name;
                $fqClass = $this->resolveName($use->uses[0]->name, '', []);

                if (!is_null($use->uses[0]->alias)) {
                    $alias = $use->uses[0]->alias->name;
                } elseif (str_contains($fqClass, '\\')) {
                    $alias = substr($fqClass, strrpos($fqClass, '\\') + 1);
                } else {
                    $alias = $fqClass;
                }

                $uses[$alias] = [
                    'class' => $fqClass,
                    'name' => $name,
                    'alias' => $alias,
                ];
            }
        }

        // Process grouped uses
        if (count($groupedUses)) {
            foreach ($groupedUses as $group) {
                $prefix = (string) $group->prefix;

                foreach ($group->uses as $use) {
                    $name = (string) $use->name;
                    $fqClass = $this->resolveName($use->name, $prefix, []);

                    if (!is_null($use->alias)) {
                        $alias = $use->alias->name;
                    } elseif (str_contains($fqClass, '\\')) {
                        $alias = substr($fqClass, strrpos($fqClass, '\\') + 1);
                    } else {
                        $alias = $fqClass;
                    }

                    $uses[$alias] = [
                        'class' => $fqClass,
                        'name' => $name,
                        'alias' => $alias,
                    ];
                }
            }
        }

        return $uses;
    }

    /**
     * Parse a class node and extract constants, properties and methods.
     *
     * @param \PhpParser\Node\Stmt\Class_ $class
     * @param string $namespace
     * @param array $uses
     * @param array $methodsWithEvents
     * @return array
     */
    protected function parseClassNode(\PhpParser\Node\Stmt\Class_ $class, string $namespace, array $uses = [], array $methodsWithEvents = [])
    {
        $name = (string) $class->name;
        $fqClass = $namespace . '\\' . $class->name;
        $extends = (!is_null($class->extends))
            ? $this->resolveName($class->extends, $namespace, $uses)
            : null;
        $implements = array_map(function ($implement) use ($namespace, $uses) {
            return $this->resolveName($implement, $namespace, $uses);
        }, $class->implements);

        if (!is_null($class->getDocComment())) {
            $docs = $this->parseDocBlock($class->getDocComment(), $namespace, $uses);
        } else {
            $docs = null;
        }

        $events = $this->parseClassEvents($class, $namespace, $uses, $methodsWithEvents);

        if (count($events)) {
            foreach ($events as $event) {
                if (!isset($this->events[$fqClass])) {
                    $this->events[$fqClass] = [];
                }
                $this->events[$fqClass][] = $event['name'];
            }
        }

        return [
            'name' => $name,
            'namespace' => $namespace,
            'type' => 'class',
            'class' => $fqClass,
            'extends' => $extends,
            'implements' => $implements,
            'uses' => $uses,
            'traits' => $this->parseClassTraits($class, $namespace, $uses),
            'docs' => $docs,
            'final' => $class->isFinal(),
            'abstract' => $class->isAbstract(),
            'constants' => $this->parseClassConstants($class, $namespace, $uses),
            'properties' => $this->parseClassProperties($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
            'events' => $events,
        ];
    }

    /**
     * Parse an interface node and extract constants, properties and methods.
     *
     * @param \PhpParser\Node\Stmt\Interface_ $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseInterfaceNode(\PhpParser\Node\Stmt\Interface_ $class, string $namespace, array $uses = [])
    {
        $name = (string) $class->name;
        $fqClass = $namespace . '\\' . $class->name;

        if (!is_null($class->getDocComment())) {
            $docs = $this->parseDocBlock($class->getDocComment(), $namespace, $uses);
        } else {
            $docs = null;
        }

        return [
            'name' => $name,
            'namespace' => $namespace,
            'type' => 'interface',
            'class' => $fqClass,
            'uses' => $uses,
            'docs' => $docs,
            'constants' => $this->parseClassConstants($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
        ];
    }

    /**
     * Parse a trait node and extract constants, properties and methods.
     *
     * @param \PhpParser\Node\Stmt\Trait_ $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseTraitNode(\PhpParser\Node\Stmt\Trait_ $class, string $namespace, array $uses = [], array $methodsWithEvents = [])
    {
        $name = (string) $class->name;
        $fqClass = $namespace . '\\' . $class->name;

        if (!is_null($class->getDocComment())) {
            $docs = $this->parseDocBlock($class->getDocComment(), $namespace, $uses);
        } else {
            $docs = null;
        }

        $events = $this->parseClassEvents($class, $namespace, $uses, $methodsWithEvents);

        if (count($events)) {
            foreach ($events as $event) {
                if (!isset($this->events[$fqClass])) {
                    $this->events[$fqClass] = [];
                }
                $this->events[$fqClass][] = $event['name'];
            }
        }

        return [
            'name' => $name,
            'namespace' => $namespace,
            'type' => 'trait',
            'class' => $fqClass,
            'uses' => $uses,
            'docs' => $docs,
            'constants' => $this->parseClassConstants($class, $namespace, $uses),
            'properties' => $this->parseClassProperties($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
            'events' => $events,
        ];
    }

    /**
     * Parse the given class constants and return documentation information.
     *
     * @param \PhpParser\Node\Stmt\ClassLike $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseClassConstants(\PhpParser\Node\Stmt\ClassLike $class, string $namespace, array $uses = [])
    {
        return array_map(function (\PhpParser\Node\Stmt\ClassConst $constant) use ($namespace, $uses) {
            $value = $this->normaliseValue($constant->consts[0]->value);

            return [
                'name' => (string) $constant->consts[0]->name,
                'type' => ($value instanceof Void_) ? [
                    'definition' => 'scalar',
                    'type' => 'mixed',
                ] : [
                    'definition' => 'scalar',
                    'type' => gettype($value),
                ],
                'value' => ($value instanceof Void_) ? null : ((string) json_encode($value ?? null)),
                'docs' => $this->parseDocBlock($constant->getDocComment(), $namespace, $uses),
                'line' => $constant->getStartLine(),
                'inherited' => false,
            ];
        }, $class->getConstants());
    }

    /**
     * Parse the given class properties and return documentation information.
     *
     * @param \PhpParser\Node\Stmt\ClassLike $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseClassProperties(\PhpParser\Node\Stmt\ClassLike $class, string $namespace, array $uses = [])
    {
        return array_map(function (\PhpParser\Node\Stmt\Property $property) use ($namespace, $uses) {
            $defaultValue = new Void_;
            if (isset($property->props[0]->default)) {
                $defaultValue = $this->normaliseValue($property->props[0]->default);
            }

            return [
                'name' => (string) $property->props[0]->name,
                'static' => $property->isStatic(),
                'type' => $this->getPropertyType($property, $namespace, $uses),
                'visibility' => ($property->isPublic())
                    ? 'public'
                    : (($property->isProtected()) ? 'protected' : 'private'),
                'default' => ($defaultValue instanceof Void_) ? null : json_encode($defaultValue),
                'docs' => $this->parseDocBlock($property->getDocComment(), $namespace, $uses),
                'line' => $property->getStartLine(),
                'inherited' => false,
            ];
        }, $class->getProperties());
    }

    /**
     * Parse the given class methods and return documentation information.
     *
     * @param \PhpParser\Node\Stmt\ClassLike $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseClassMethods(\PhpParser\Node\Stmt\ClassLike $class, string $namespace, array $uses = [])
    {
        return array_map(function (\PhpParser\Node\Stmt\ClassMethod $method) use ($namespace, $uses) {
            $docs = $this->parseDocBlock($method->getDocComment(), $namespace, $uses);

            return [
                'name' => (string) $method->name,
                'final' => $method->isFinal(),
                'static' => $method->isStatic(),
                'abstract' => $method->isAbstract(),
                'returns' => $this->getReturnType($method, $namespace, $uses),
                'visibility' => ($method->isPublic())
                    ? 'public'
                    : (($method->isProtected()) ? 'protected' : 'private'),
                'docs' => $docs,
                'params' => $this->processMethodParams($method, $namespace, $uses, $docs),
                'lines' => [$method->getStartLine(), $method->getEndLine()],
                'inherited' => false,
            ];
        }, $class->getMethods());
    }

    /**
     * Processes the params of a given method and returns an array of documentation for the param.
     *
     * @param FunctionLike $class
     * @param string $namespace
     * @param array $uses
     * @param array|null $docs
     * @return array
     */
    protected function processMethodParams(FunctionLike $method, string $namespace, array $uses = [], ?array $docs = [])
    {
        return array_map(function (Param $param) use ($namespace, $uses, $docs) {
            $type = $this->getParamType($param, $namespace, $uses, $docs);

            $defaultValue = new Void_;
            if (isset($param->default)) {
                $defaultValue = $this->normaliseValue($param->default);
            }

            return [
                'name' => (string) $param->var->name,
                'type' => $type['type'],
                'summary' => $type['summary'],
                'default' => ($defaultValue instanceof Void_) ? null : json_encode($defaultValue),
            ];
        }, $method->getParams());
    }

    /**
     * Processes the params of a given event and returns an array of documentation for the param.
     *
     * @param array $docs
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function processEventParams(array $docs, string $namespace, array $uses = [])
    {
        return array_map(function ($key, $value) use ($namespace, $uses, $docs) {
            return [
                'name' => $key,
                'type' => $value['type'],
                'summary' => $value['summary'],
                'default' => null,
            ];
        }, array_keys($docs['params'] ?? []), array_values($docs['params'] ?? []));
    }

    /**
     * Parse the given class traits and return documentation information.
     *
     * @param \PhpParser\Node\Stmt\ClassLike $class
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseClassTraits(\PhpParser\Node\Stmt\ClassLike $class, string $namespace, array $uses = [])
    {
        return array_map(function ($trait) use ($namespace, $uses) {
            return $this->resolveName($trait->traits[0], $namespace, $uses);
        }, $class->getTraitUses());
    }

    /**
     * Parse the given class for event docblocks.
     *
     * @param \PhpParser\Node\Stmt\ClassLike $class
     * @param string $namespace
     * @param array $uses
     * @param array $methodsWithEvents
     * @return array
     */
    protected function parseClassEvents(\PhpParser\Node\Stmt\ClassLike $class, string $namespace, array $uses = [], array $methodsWithEvents = [])
    {
        return array_filter(array_map(function ($methodEvent) use ($class, $namespace, $uses) {
            $event = $this->parseEvent($methodEvent->getDocComment());

            if (is_null($event)) {
                return null;
            }

            $docs = $this->parseDocBlock($event['docBlock'], $namespace, $uses);

            return [
                'name' => $event['name'],
                'method' => (string) $methodEvent->name,
                'params' => $this->processEventParams($docs, $namespace, $uses),
                'docs' => $docs,
                'lines' => [$methodEvent->getDocComment()->getStartLine(), $methodEvent->getDocComment()->getEndLine()],
            ];
        }, $methodsWithEvents));
    }

    /**
     * Parse a docblock comment and extract the documentation.
     *
     * @param string|null $comment
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function parseDocBlock(?string $comment, string $namespace, array $uses = [])
    {
        if (is_null($comment)) {
            return null;
        }

        if (!isset($this->docBlockFactory)) {
            $this->docBlockFactory = DocBlockFactory::createInstance();
        }

        $docBlock = $this->docBlockFactory->create($comment);

        // Determine if this is an inherit block
        if ($docBlock->getSummary() === '{@inheritDoc}') {
            return [
                'inherit' => true,
            ];
        }
        foreach ($docBlock->getTags() as $tag) {
            if ($tag instanceof Generic && $tag->getName() === 'inheritDoc') {
                return [
                    'inherit' => true,
                ];
            }
        }

        // Get main info
        $details = [
            'summary' => Markdown::parse($docBlock->getSummary()),
            'body' => Markdown::parse($docBlock->getDescription()->render()),
            'since' => (count($docBlock->getTagsByName('since')))
                ? $docBlock->getTagsByName('since')[0]->getVersion() ?? null
                : null,
            'deprecated' => (count($docBlock->getTagsByName('deprecated')))
                ? $docBlock->getTagsByName('deprecated')[0]->getVersion() ?? null
                : null,
        ];

        // Find authors
        if (count($docBlock->getTagsByName('author'))) {
            /** @var \phpDocumentor\Reflection\DocBlock\Tags\Author */
            foreach ($docBlock->getTagsByName('author') as $tag) {
                if (!$tag instanceof Author) {
                    continue;
                }

                $details['authors'][] = [
                    'name' => $tag->getAuthorName(),
                    'email' => $tag->getEmail(),
                ];
            }
        }

        // Find vars
        if (count($docBlock->getTagsByName('var'))) {
            $var = $docBlock->getTagsByName('var')[0];

            if ($var instanceof InvalidTag) {
                $details['summary'] = (string) $var;
                $details['var'] = [
                    'type' => [
                        'definition' => 'scalar',
                        'type' => 'mixed',
                    ],
                ];
            } else {
                if (empty($details['summary']) && !empty($var->getDescription())) {
                    $details['summary'] = Markdown::parse($var->getDescription()->render());
                }

                $details['var'] = [
                    'type' => $this->getDocType($var->getType(), $namespace, $uses),
                ];
            }
        }

        // Find params
        if (count($docBlock->getTagsByName('param'))) {
            /** @var \phpDocumentor\Reflection\DocBlock\Tags\Param */
            foreach ($docBlock->getTagsByName('param') as $key => $tag) {
                if ($tag instanceof InvalidTag) {
                    $details['params'][(string) $key] = [
                        'type' => [
                            'definition' => 'scalar',
                            'type' => 'mixed',
                        ],
                        'summary' => (string) $tag,
                    ];
                } else {
                    $details['params'][$tag->getVariableName()] = [
                        'type' => $this->getDocType($tag->getType(), $namespace, $uses),
                        'summary' => Markdown::parse($tag->getDescription()->render()),
                    ];
                }
            }
        }

        // Find params
        if (count($docBlock->getTagsByName('throws'))) {
            /** @var \phpDocumentor\Reflection\DocBlock\Tags\Throws */
            foreach ($docBlock->getTagsByName('throws') as $tag) {
                $details['throws'][] = [
                    'type' => $this->getDocType($tag->getType(), $namespace, $uses),
                    'summary' => Markdown::parse($tag->getDescription()->render()),
                ];
            }
        }


        // Find return
        if (count($docBlock->getTagsByName('return'))) {
            $return = $docBlock->getTagsByName('return')[0];

            if ($return instanceof InvalidTag) {
                $details['return'] = [
                    'type' => [
                        'definition' => 'scalar',
                        'type' => 'mixed',
                    ],
                    'summary' => (string) $return,
                ];
            } else {
                $details['return'] = [
                    'type' => $this->getDocType($return->getType(), $namespace, $uses),
                    'summary' => Markdown::parse($return->getDescription()->render()),
                ];
            }
        }

        return array_filter($details, function ($item, $key) {
            if (in_array($key, ['summary', 'body'])) {
                return !empty(trim($item));
            }
            return !is_null($item);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Parses an event docblock, extracts the name and removes it so it can be parsed by the DocBlock parser.
     *
     * @param Doc $docBlock
     * @return array
     */
    protected function parseEvent(Doc $docBlock)
    {
        $text = $docBlock->getReformattedText();

        // Get event name before stripping out tag
        preg_match('/^( +\* |\/\*\* )@event +([^ ]+)/m', $text, $matches);
        $eventName = $matches[2] ?? null;

        if (is_null($eventName)) {
            return null;
        }

        if (preg_match('/^\/\*\* @event.*?$/m', $text)) {
            // Remove an event tag at the start of a docblock
            $text = preg_replace('/^\/\*\* @event.*?$/m', '/**', $text);
        } else {
            // Remove an event tag within the docblock, plus an empty line underneath if it exists
            $text = preg_replace('/^( +\* )@event.*?[\n\r]+( +\* *[\n\r]+)?/m', '', $text);
        }

        return [
            'name' => $eventName,
            'docBlock' => $text,
        ];
    }

    /**
     * Extract the type of property or variable from the documentation.
     *
     * For single types, this will return a string. For multiple types, this will return an array.
     *
     * @param Type|null $class
     * @param string $namespace
     * @param array $uses
     * @return array|string
     */
    protected function getDocType(?Type $type, string $namespace, array $uses = [])
    {
        if (is_null($type)) {
            return [
                'definition' => 'scalar',
                'type' => 'mixed',
            ];
        }

        // Handle compound types
        if ($type instanceof Compound) {
            $types = [];

            foreach ($type as $item) {
                if ($item instanceof Object_ && !is_null($item->getFqsen())) {
                    $resolved = ltrim((string) $item->getFqsen(), '\\');
                    $types[] = [
                        'definition' => 'reference',
                        'type' => [
                            'name' => $resolved,
                            'class' => $resolved,
                            'linked' => false,
                        ],
                    ];
                } else {
                    $resolved = $this->resolveName((string) $item, $namespace, $uses);
                    if ($this->isScalar($resolved)) {
                        $types[] = [
                            'definition' => 'scalar',
                            'type' => $resolved,
                        ];
                    } else {
                        $types[] = [
                            'definition' => 'reference',
                            'type' => [
                                'name' => $resolved,
                                'class' => $resolved,
                                'linked' => false,
                            ],
                        ];
                    }
                }
            }

            return [
                'definition' => 'union',
                'types' => $types,
            ];
        }

        if ($type instanceof Object_ && !is_null($type->getFqsen())) {
            $resolved = ltrim((string) $type->getFqsen(), '\\');
            return [
                'definition' => 'reference',
                'type' => [
                    'name' => $resolved,
                    'class' => $resolved,
                    'linked' => false,
                ],
            ];
        } else {
            $resolved = $this->resolveName((string) $type, $namespace, $uses);
            if ($this->isScalar($resolved)) {
                return [
                    'definition' => 'scalar',
                    'type' => $resolved,
                ];
            } else {
                return [
                    'definition' => 'reference',
                    'type' => [
                        'name' => $resolved,
                        'class' => $resolved,
                        'linked' => false,
                    ],
                ];
            }
        }
    }

    /**
     * Resolves a class name and ensures that it is fully qualified.
     *
     * @param mixed $name
     * @param string $namespace
     * @param array $uses
     * @param mixed $alias
     * @return string
     */
    protected function resolveName($name, string $namespace, array $uses = [], $alias = null)
    {
        // If this is a scalar reference, return as is
        if ($this->isScalar($name)) {
            return (string) $name;
        }

        // If this name is part of a use case, use that as the name
        if (array_key_exists((string) $name, $uses)) {
            return $uses[(string) $name]['class'];
        }

        if ($name instanceof \PhpParser\Node\Name\FullyQualified) {
            return $name->toString();
        } elseif ($name instanceof \PhpParser\Node\Name\Relative) {
            return $namespace . '\\' . $name->toString();
        } elseif (!is_null($alias)) {
            if (array_key_exists((string) $alias, $uses)) {
                return $uses[(string) $alias]['class'];
            } else {
                return (string) $alias;
            }
        } else {
            return (string) $name;
        }
    }

    /**
     * Gets the resolved type for a property.
     *
     * Types are determined by the first specific type found in order of the below:
     *   - Strict type declaration
     *   - Default value
     *   - Docblock specified type
     *
     * @param Property $property
     * @param string $namespace
     * @param array $uses
     * @return array|string
     */
    protected function getPropertyType(Property $property, string $namespace, array $uses = [])
    {
        if (!is_null($property->type)) {
            if ($property->type instanceof NullableType) {
                return $this->normaliseType([
                    $this->resolveName($property->type->type, $namespace, $uses),
                    'null',
                ]);
            } elseif ($property->type instanceof UnionType) {
                $types = [];

                foreach ($property->type->types as $item) {
                    $types[] = $this->resolveName((string) $item, $namespace, $uses);
                }

                return $this->normaliseType($types);
            }

            return $this->normaliseType($this->resolveName($property->type, $namespace, $uses));
        }

        if (!is_null($property->props[0]->default)) {
            $defaultType = $this->normaliseType($property->props[0]->default);
            if ($defaultType['definition'] !== 'scalar' && $defaultType['type'] !== 'mixed') {
                return $defaultType;
            }
        }

        $docs = $this->parseDocBlock($property->getDocComment(), $namespace, $uses);

        if (!empty($docs['var'])) {
            return $docs['var']['type'];
        }

        return [
            'definition' => 'scalar',
            'type' => 'mixed',
        ];
    }

    /**
     * Gets the resolved type for a method parameter.
     *
     * Types are determined by the first specific type found in order of the below:
     *   - Strict type declaration
     *   - Default value
     *   - Docblock specified type
     *
     * @param Param $param
     * @param string $namespace
     * @param array $uses
     * @param array|null $docs
     * @return array|string
     */
    protected function getParamType(Param $param, string $namespace, array $uses = [], ?array $docs = [])
    {
        $type = [
            'definition' => 'scalar',
            'type' => 'mixed',
        ];
        $summary = null;

        if (!is_null($param->type)) {
            if ($param->type instanceof NullableType) {
                $type = $this->normaliseType([
                    $this->resolveName($param->type->type, $namespace, $uses),
                    'null',
                ]);
            } elseif ($param->type instanceof UnionType) {
                $types = [];

                foreach ($param->type->types as $item) {
                    $types[] = $this->resolveName((string) $item, $namespace, $uses);
                }

                $type = $this->normaliseType($types);
            } else {
                $type = $this->normaliseType($this->resolveName($param->type, $namespace, $uses));
            }
        }

        if (!is_null($param->default)) {
            $type = $this->normaliseType($param->default);
        }

        if (!empty($docs) && !empty($docs['params'][(string) $param->var->name])) {
            $type = ($type['definition'] === 'scalar' && $type['type'] === 'mixed') ? $docs['params'][(string) $param->var->name]['type'] : $type;
            $summary = $docs['params'][(string) $param->var->name]['summary'] ?? null;
        }

        return [
            'type' => $type,
            'summary' => $summary,
        ];
    }

    /**
     * Gets the return type for a method.
     *
     * Types are determined by the first specific type found in order of the below:
     *   - Return type
     *   - Docblock specified type
     *
     * @param FunctionLike $method
     * @param string $namespace
     * @param array $uses
     * @return array
     */
    protected function getReturnType(FunctionLike $method, string $namespace, array $uses = [])
    {
        $type = [
            'definition' => 'scalar',
            'type' => 'mixed',
        ];
        $summary = null;

        if (!is_null($method->returnType)) {
            if ($method->returnType instanceof NullableType) {
                $type = $this->normaliseType([
                    $this->resolveName($method->returnType->type, $namespace, $uses),
                    'null',
                ]);
            } elseif ($method->returnType instanceof UnionType) {
                $types = [];

                foreach ($method->returnType->types as $item) {
                    $types[] = $this->resolveName((string) $item, $namespace, $uses);
                }

                $type = $this->normaliseType($types);
            } else {
                $type = $this->normaliseType($this->resolveName($method->returnType, $namespace, $uses));
            }
        }

        $docs = $this->parseDocBlock($method->getDocComment(), $namespace, $uses);

        if (!empty($docs['return'])) {
            $type = ($type['definition'] === 'scalar' && $type['type'] === 'mixed') ? $docs['return']['type'] : $type;
            $summary = $docs['return']['summary'];
        }

        return [
            'type' => $type,
            'summary' => $summary,
        ];
    }

    /**
     * Returns if the name of this node or string is a scalar type.
     *
     * @param mixed $name
     * @return bool
     */
    protected function isScalar($name)
    {
        $scalars = [
            'bool',
            'int',
            'integer',
            'float',
            'double',
            'string',
            'array',
            'object',
            'callable',
            'iterable',
            'resource',
            'null'
        ];

        if (is_object($name) && !method_exists($name, '__toString')) {
            return false;
        }

        return in_array((string) $name, $scalars);
    }

    /**
     * Normalise type names.
     *
     * @return array Returns an array that may contain the following:
     *   - `definition` - either `union` for a union type, `reference` for a class reference and `scalar` for a scalar
     *        type.
     *   - `types`: If a union type, this will be an array of single types using this same structure.
     *   - `type`: The details of the type. Either a string for a scalar type, or an array with the following for a
     *        reference: `name` for the short reference name, `class` for the full reference name and `linked`, whether
     *        this reference can be linked or not
     */
    protected function normaliseType($type): array
    {
        if (is_array($type) && Arr::isList($type)) {
            return [
                'definition' => 'union',
                'types' => array_map(function ($item) {
                    return $this->normaliseType($item);
                }, $type)
            ];
        }

        if ($type instanceof \PhpParser\Node) {
            if ($type instanceof \PhpParser\Node\Expr\Array_) {
                return [
                    'definition' => 'scalar',
                    'type' => 'array',
                ];
            }

            if ($type instanceof \PhpParser\Node\Scalar\String_) {
                return [
                    'definition' => 'scalar',
                    'type' => 'string',
                ];
            }

            $value = $this->normaliseValue($type);

            if ($value instanceof Void_ || $value === null) {
                return [
                    'definition' => 'scalar',
                    'type' => 'mixed',
                ];
            }

            return [
                'definition' => 'scalar',
                'type' => gettype($value),
            ];
        } elseif (is_string($type)) {
            if (strpos('\\', $type)) {
                return [
                    'definition' => 'reference',
                    'type' => [
                        'name' => $type,
                        'class' => $type,
                        'linked' => false,
                    ],
                ];

                return $type;
            }
        } else {
            $type = gettype($type);
        }

        switch ($type) {
            case 'int':
                $type = 'integer';
                break;
        }

        return [
            'definition' => 'scalar',
            'type' => $type,
        ];
    }

    /**
     * Normalises a value.
     *
     * @param mixed $value
     * @return mixed Returns a value if it can be normalised, otherwise this will return a "Void"
     *   instance to represent an absence of a value (including `null`)
     */
    protected function normaliseValue($value): mixed
    {
        $evaluator = new ConstExprEvaluator(function ($value) {
            if ($value instanceof \PhpParser\Node\Expr\ClassConstFetch) {
                $const = $value->name;

                // Class identifier constant
                if ($const === 'class') {
                    return $value->name->toString();
                }

                return new Void_;
            }
        });

        try {
            return $evaluator->evaluateSilently($value);
        } catch (ConstExprEvaluationException $e) {
            return new Void_;
        }
    }

    /**
     * Determines if the current docblock appears to be an event docblock.
     *
     * @param Doc $docBlock
     * @return boolean
     */
    protected function isEventDocBlock(Doc $docBlock)
    {
        $text = $docBlock->getReformattedText();
        return preg_match('/^( +\* |\/\*\* )@event/m', $text) === 1;
    }

    /**
     * Processes the inherited properties, constants and methods of each class.
     *
     * @return void
     */
    protected function processInheritance()
    {
        foreach ($this->classes as $name => &$class) {
            if ($class['type'] === 'interface') {
                // Interfaces do not inherit anything
                continue;
            }

            // Determine docs to inherit
            $class['inheritedDocs'] = [
                'properties' => [],
                'constants' => [],
                'methods' => [],
            ];

            foreach ($class['properties'] as $property) {
                if (isset($property['docs']['inherit']) && $property['docs']['inherit'] === true) {
                    $class['inheritedDocs']['properties'][] = $property['name'];
                }
            }
            foreach ($class['constants'] as $constant) {
                if (isset($constant['docs']['inherit']) && $constant['docs']['inherit'] === true) {
                    $class['inheritedDocs']['constants'][] = $constant['name'];
                }
            }
            foreach ($class['methods'] as $method) {
                if (isset($method['docs']['inherit']) && $method['docs']['inherit'] === true) {
                    $class['inheritedDocs']['methods'][] = $method['name'];
                }
            }

            // Get inherited methods, constants and properties
            if (
                !isset($class['extends'])
                && empty($class['traits'])
                && empty($class['implements'])
            ) {
                unset($class['inheritedDocs']);

                // No inheritance for this class
                continue;
            }

            // Local traits are applied first in the inheritance chain
            if (isset($class['traits']) && count($class['traits'])) {
                foreach ($class['traits'] as $trait) {
                    if (isset($this->classes[$trait])) {
                        $this->processSingleInheritance($class, $this->classes[$trait]);
                    }
                }
            }

            // Next, the parent class is inherited.
            if (isset($class['extends']) && isset($this->classes[$class['extends']])) {
                $this->processSingleInheritance($class, $this->classes[$class['extends']]);
            }

            // We'll also inherit the implements, but mainly for the docs, since all its methods
            // SHOULD be overridden already
            if (isset($class['implements']) && count($class['implements'])) {
                foreach ($class['implements'] as $implements) {
                    if (isset($this->classes[$implements])) {
                        $this->processSingleInheritance($class, $this->classes[$implements]);
                    }
                }
            }
        }

        // Run a second pass for inherited statements that are using @inheritDoc tags.
        $this->secondPassInheritedDocs();
    }

    /**
     * Processes a single class inheritance.
     *
     * This method is looping, to allow multiple levels of extends or traits. This method also collates inherited docs
     * for the purpose of filling in any @inheritDoc calls.
     *
     * @param array $child
     * @param array $ancestor
     * @return void
     */
    protected function processSingleInheritance(array &$child, array $ancestor)
    {
        // Compare methods, constants and properties of the parent and inherit anything not overwritten by the child
        // (or already inherited)

        // Traits
        if ($ancestor['type'] === 'class' && count($ancestor['traits'])) {
            foreach ($ancestor['traits'] as $trait) {
                if (!in_array($trait, $child['traits'])) {
                    $child['traits'][] = $trait;
                }
            }
        }

        // Interfaces
        if ($ancestor['type'] === 'class' && count($ancestor['implements'])) {
            foreach ($ancestor['implements'] as $interface) {
                if (!in_array($interface, $child['implements'])) {
                    $child['implements'][] = $interface;
                }
            }
        }

        // Methods
        $childMethods = array_map(function ($method) {
            return $method['name'];
        }, $child['methods'] ?? []);

        $ancestorMethods = array_map(function ($method) {
            return $method['name'];
        }, $ancestor['methods'] ?? []);

        $inheritedMethods = array_diff($ancestorMethods, $childMethods);

        if (count($inheritedMethods)) {
            foreach ($inheritedMethods as $method) {
                $child['methods'][] = array_replace(
                    array_first($ancestor['methods'], function ($ancestorMethod) use ($method) {
                        return $ancestorMethod['name'] === $method;
                    }),
                    [
                        'inherited' => [
                            'name' => $ancestor['name'],
                            'class' => $ancestor['class'],
                        ],
                    ]
                );
            }
        }

        // Determine inherited method docs
        if (count($child['inheritedDocs']['methods'])) {
            foreach ($child['inheritedDocs']['methods'] as $key => $method) {
                if (in_array($method, $ancestorMethods)) {
                    $ancestorMethod = array_first($ancestor['methods'], function ($ancestorMethod) use ($method) {
                        return $ancestorMethod['name'] === $method;
                    });

                    if (!isset($ancestorMethod['docs']['inherit']) || $ancestorMethod['docs']['inherit'] === false) {
                        foreach ($child['methods'] as $i => $childMethod) {
                            if ($childMethod['name'] === $method) {
                                $child['methods'][$i]['docs'] = $ancestorMethod['docs'];
                                $this->processInheritedDocs('method', $child['methods'][$i]);
                                break;
                            }
                        }

                        array_splice($child['inheritedDocs']['methods'], $key, 1);
                    }
                }
            }
        }

        // Properties
        $childProps = array_map(function ($property) {
            return $property['name'];
        }, $child['properties'] ?? []);

        $ancestorProps = array_map(function ($property) {
            return $property['name'];
        }, $ancestor['properties'] ?? []);

        $inheritedProps = array_diff($ancestorProps, $childProps);

        if (count($inheritedProps)) {
            foreach ($inheritedProps as $property) {
                $child['properties'][] = array_replace(
                    array_first($ancestor['properties'], function ($ancestorProp) use ($property) {
                        return $ancestorProp['name'] === $property;
                    }),
                    [
                        'inherited' => [
                            'name' => $ancestor['name'],
                            'class' => $ancestor['class'],
                        ],
                    ]
                );
            }
        }

        // Determine inherited property docs
        if (count($child['inheritedDocs']['properties'])) {
            foreach ($child['inheritedDocs']['properties'] as $key => $prop) {
                if (in_array($prop, $ancestorProps)) {
                    $ancestorProp = array_first($ancestor['properties'], function ($ancestorProp) use ($prop) {
                        return $ancestorProp['name'] === $prop;
                    });

                    if (!isset($ancestorProp['docs']['inherit']) || $ancestorProp['docs']['inherit'] === false) {
                        foreach ($child['properties'] as $i => $childProp) {
                            if ($childProp['name'] === $prop) {
                                $child['properties'][$i]['docs'] = $ancestorProp['docs'];
                                $this->processInheritedDocs('property', $child['properties'][$i]);
                                break;
                            }
                        }

                        array_splice($child['inheritedDocs']['properties'], $key, 1);
                    }
                }
            }
        }

        $childConsts = array_map(function ($constant) {
            return $constant['name'];
        }, $child['constants'] ?? []);

        $ancestorConsts = array_map(function ($constant) {
            return $constant['name'];
        }, $ancestor['constants'] ?? []);

        $inheritedConsts = array_diff($ancestorConsts, $childConsts);

        if (count($inheritedConsts)) {
            foreach ($inheritedConsts as $constant) {
                $child['constants'][] = array_replace(
                    array_first($ancestor['constants'], function ($ancestorConst) use ($constant) {
                        return $ancestorConst['name'] === $constant;
                    }),
                    [
                        'inherited' => [
                            'name' => $ancestor['name'],
                            'class' => $ancestor['class'],
                        ],
                    ]
                );
            }
        }

        // Determine inherited constant docs
        if (count($child['inheritedDocs']['constants'])) {
            foreach ($child['inheritedDocs']['constants'] as $key => $const) {
                if (in_array($const, $ancestorConsts)) {
                    $ancestorConst = array_first($ancestor['constants'], function ($ancestorConst) use ($const) {
                        return $ancestorConst['name'] === $const;
                    });

                    if (!isset($ancestorConst['docs']['inherit']) || $ancestorConst['docs']['inherit'] === false) {
                        foreach ($child['constants'] as $i => $childConst) {
                            if ($childConst['name'] === $const) {
                                $child['constants'][$i]['docs'] = $ancestorConst['docs'];
                                $this->processInheritedDocs('constant', $child['constants'][$i]);
                                break;
                            }
                        }

                        array_splice($child['inheritedDocs']['constants'], $key, 1);
                    }
                }
            }
        }


        // Find out if the parent also inherits anything
        if (
            $ancestor['type'] === 'class'
            && (
                !is_null($ancestor['extends'])
                || count($ancestor['traits'])
                || count($ancestor['implements'])
            )
        ) {
            if (count($ancestor['traits'])) {
                foreach ($ancestor['traits'] as $trait) {
                    if (isset($this->classes[$trait])) {
                        $this->processSingleInheritance($child, $this->classes[$trait]);
                    }
                }
            }

            if (!is_null($ancestor['extends']) && isset($this->classes[$ancestor['extends']])) {
                $this->processSingleInheritance($child, $this->classes[$ancestor['extends']]);
            }

            if (count($ancestor['implements'])) {
                foreach ($ancestor['implements'] as $implements) {
                    if (isset($this->classes[$implements])) {
                        $this->processSingleInheritance($child, $this->classes[$implements]);
                    }
                }
            }
        }
    }

    /**
     * Resolve @inheritDoc docblocks in "local" statements.
     *
     * @param string $type
     * @param array $data
     * @return void
     */
    protected function processInheritedDocs(string $type, array &$data)
    {
        // Inherited method docs will overwrite the returns and params if any of them are missing a summary or are
        // using the "mixed" type.
        if ($type === 'method') {
            if (
                (
                    $data['returns']['type']['definition'] === 'single'
                    && $data['returns']['type']['type'] === 'mixed'
                )
                && (
                    isset($data['docs']['returns']['type'])
                    && (
                        $data['docs']['returns']['type']['definition'] !== 'single'
                        || $data['docs']['returns']['type']['type'] !== 'mixed'
                    )
                )
            ) {
                $data['returns']['type'] = $data['docs']['returns']['type'];
            }
            if (empty($data['returns']['summary']) && !empty($data['docs']['return']['summary'])) {
                $data['returns']['summary'] = $data['docs']['return']['summary'];
            }

            foreach ($data['params'] as &$param) {
                if (
                    (
                        $param['type']['definition'] === 'single'
                        && $param['type']['type'] === 'mixed'
                    )
                    && (
                        isset($param['docs']['params'][$param['name']]['type'])
                        && (
                            $param['docs']['params'][$param['name']]['type']['definition'] !== 'single'
                            || $param['docs']['params'][$param['name']]['type']['type'] !== 'mixed'
                        )
                    )
                ) {
                    $param['type'] = $data['docs']['params'][$param['name']]['type'];
                }
                if (empty($param['summary']) && !empty($data['docs']['params'][$param['name']]['summary'])) {
                    $param['summary'] = $data['docs']['params'][$param['name']]['summary'];
                }
            }
        }
    }

    protected function secondPassInheritedDocs()
    {
        foreach ($this->classes as $name => &$class) {
            // No inheritance - continue
            if (!isset($class['inherited'])) {
                continue;
            }

            if (isset($class['inherited']['methods'])) {
                foreach ($class['inherited']['methods'] as &$method) {
                    if (isset($method['method']['docs']['inherit']) && $method['method']['docs']['inherit'] === true) {
                        $ancestorMethod = array_first($this->classes[$method['class']]['methods'], function ($ancestorMethod) use ($method) {
                            return $ancestorMethod['name'] === $method['method']['name'];
                        });

                        if (!empty($ancestorMethod)) {
                            $method['method']['docs'] = $ancestorMethod['docs'];
                            $this->processInheritedDocs('method', $method['method']);
                        }
                    }
                }
            }

            if (isset($class['inherited']['properties'])) {
                foreach ($class['inherited']['properties'] as &$property) {
                    if (isset($property['property']['docs']['inherit']) && $property['property']['docs']['inherit'] === true) {
                        $ancestorProp = array_first($this->classes[$property['class']]['properties'], function ($ancestorProp) use ($property) {
                            return $ancestorProp['name'] === $property['property']['name'];
                        });

                        if (!empty($ancestorProp)) {
                            $property['property']['docs'] = $ancestorProp['docs'];
                            $this->processInheritedDocs('property', $property['property']);
                        }
                    }
                }
            }

            if (isset($class['inherited']['constants'])) {
                foreach ($class['inherited']['constants'] as &$const) {
                    if (isset($const['constant']['docs']['inherit']) && $const['constant']['docs']['inherit'] === true) {
                        $ancestorConst = array_first($this->classes[$const['class']]['constants'], function ($ancestorConst) use ($const) {
                            return $ancestorConst['name'] === $const['constant']['name'];
                        });

                        if (!empty($ancestorConst)) {
                            $const['constant']['docs'] = $ancestorConst['docs'];
                            $this->processInheritedDocs('constant', $const['constant']);
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds additional context to some properties.
     *
     * @return void
     */
    protected function processContext()
    {
        foreach ($this->classes as $name => &$class) {
            // Link class references in properties
            if (!empty($class['properties'])) {
                foreach ($class['properties'] as &$property) {
                    if ($property['type']['definition'] === 'union') {
                        foreach ($property['type']['types'] as &$type) {
                            if ($type['definition'] === 'reference') {
                                if (!empty($class['uses']) && isset($class['uses'][$type['type']['class']])) {
                                    $type['type']['class'] = $class['uses'][$type['type']['class']]['class'];
                                }
                                if (isset($this->classes[$type['type']['class']])) {
                                    $type['type']['linked'] = true;
                                    continue;
                                }
                            }
                        }
                    } elseif ($property['type']['definition'] === 'reference') {
                        if (!empty($class['uses']) && isset($class['uses'][$property['type']['type']['class']])) {
                            $property['type']['type']['class'] = $class['uses'][$property['type']['type']['class']]['class'];
                        }
                        if (isset($this->classes[$property['type']['type']['class']])) {
                            $property['type']['type']['linked'] = true;
                            continue;
                        }
                    }
                }
            }

            // Link class references in method param and return types
            if (!empty($class['methods'])) {
                foreach ($class['methods'] as &$method) {
                    if (!empty($method['params'])) {
                        foreach ($method['params'] as &$param) {
                            if ($param['type']['definition'] === 'union') {
                                foreach ($param['type']['types'] as &$type) {
                                    if ($type['definition'] === 'reference') {
                                        if (!empty($class['uses']) && isset($class['uses'][$type['type']['class']])) {
                                            $type['type']['class'] = $class['uses'][$type['type']['class']]['class'];
                                        }
                                        if (isset($this->classes[$type['type']['class']])) {
                                            $type['type']['linked'] = true;
                                            continue;
                                        }
                                    }
                                }
                            } elseif ($param['type']['definition'] === 'reference') {
                                if (!empty($class['uses']) && isset($class['uses'][$param['type']['type']['class']])) {
                                    $param['type']['type']['class'] = $class['uses'][$param['type']['type']['class']]['class'];
                                }
                                if (isset($this->classes[$param['type']['type']['class']])) {
                                    $param['type']['type']['linked'] = true;
                                    continue;
                                }
                            }
                        }
                    }

                    if (!empty($method['returns']['type'])) {
                        if ($method['returns']['type']['definition'] === 'union') {
                            foreach ($method['returns']['type']['types'] as &$type) {
                                if ($type['definition'] === 'reference') {
                                    if (!empty($class['uses']) && isset($class['uses'][$type['type']['class']])) {
                                        $type['type']['class'] = $class['uses'][$type['type']['class']]['class'];
                                    }
                                    if (isset($this->classes[$type['type']['class']])) {
                                        $type['type']['linked'] = true;
                                        continue;
                                    }
                                }
                            }
                        } elseif ($method['returns']['type']['definition'] === 'reference') {
                            if (!empty($class['uses']) && isset($class['uses'][$method['returns']['type']['type']['class']])) {
                                $method['returns']['type']['type']['class'] = $class['uses'][$method['returns']['type']['type']['class']]['class'];
                            }
                            if (isset($this->classes[$method['returns']['type']['type']['class']])) {
                                $method['returns']['type']['type']['linked'] = true;
                                continue;
                            }
                        }
                    }
                }
            }

            if (isset($class['traits']) && count($class['traits'])) {
                foreach ($class['traits'] as &$trait) {
                    if (isset($this->classes[$trait])) {
                        $trait = [
                            'name' => $this->classes[$trait]['name'] ?? $this->classes[$trait]['class'],
                            'class' => $this->classes[$trait]['class'],
                            'summary' => $this->classes[$trait]['docs']['summary']
                                ?? $this->classes[$trait]['docs']['body']
                                ?? null,
                        ];
                    } else {
                        $trait = [
                            'name' => $trait,
                            'class' => $trait,
                        ];
                    }
                }
            }

            $this->sortDefinitions($class);
        }
    }

    /**
     * Sorts definitions in a class.
     *
     * At a minimum, all listable elements will be sorted by name. In terms of properties and methods,
     * these will also be sorted by visibility and inheritence.
     *
     * @param array $class
     * @return void
     */
    protected function sortDefinitions(array &$class)
    {
        $visibilityRanks = [
            'public' => 1,
            'protected' => 2,
            'private' => 3,
        ];

        // Traits
        if (isset($class['traits']) && count($class['traits']) > 1) {
            $names = array_column($class['traits'], 'name');

            array_multisort(
                $names, SORT_STRING, SORT_ASC,
                $class['traits']
            );
        }

        // Properties
        if (isset($class['properties']) && count($class['properties']) > 1) {
            $names = array_column($class['properties'], 'name');
            $visibility = [];
            $inherited = [];

            foreach ($class['properties'] as $key => $property) {
                $inherited[$key] = ($property['inherited'] === false) ? 1 : 2;
                $visibility[$key] = $visibilityRanks[$property['visibility']];
            }

            array_multisort(
                $inherited, SORT_NUMERIC, SORT_ASC,
                $visibility, SORT_NUMERIC, SORT_ASC,
                $names, SORT_STRING, SORT_ASC,
                $class['properties']
            );
        }

        // Constants
        if (isset($class['constants']) && count($class['constants']) > 1) {
            $names = array_column($class['constants'], 'name');

            array_multisort(
                $names, SORT_STRING, SORT_ASC,
                $class['constants']
            );
        }

        // Methods
        if (isset($class['methods']) && count($class['methods']) > 1) {
            $names = array_column($class['methods'], 'name');
            $visibility = [];
            $inherited = [];

            foreach ($class['methods'] as $key => $property) {
                $inherited[$key] = ($property['inherited'] === false) ? 1 : 2;
                $visibility[$key] = $visibilityRanks[$property['visibility']];
            }

            array_multisort(
                $inherited, SORT_NUMERIC, SORT_ASC,
                $visibility, SORT_NUMERIC, SORT_ASC,
                $names, SORT_STRING, SORT_ASC,
                $class['methods']
            );
        }
    }
}
