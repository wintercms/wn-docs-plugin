<?php namespace Winter\Docs\Classes;

use Markdown;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Object_;
use PhpParser\Error;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Winter\Storm\Filesystem\Filesystem;
use Winter\Storm\Halcyon\Datasource\FileDatasource;

/**
 * PHP API Parser.
 *
 * This class will parse a directory for all PHP files and will extract the necessary information to generate
 * documentation for these files. This includes constants, properties and methods.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
class ApiParser
{
    /** @var array Base paths where files will be read */
    protected $basePaths;

    /** @var array List of file paths found */
    protected $paths = [];

    /** @var array List of namespaces encountered */
    protected $namespaces = [];

    /** @var array All classes scanned during parsing */
    protected $classes = [];

    /** @var array A list of paths that could not be parsed. */
    protected $failedPaths = [];

    /** @var DocBlockFactory Factory instance for generating DocBlock reflections */
    protected $docBlockFactory;

    /**
     * Constructor.
     *
     * @param array|string $basePath
     */
    public function __construct($basePaths = [])
    {
        $this->basePaths = (is_string($basePaths)) ? [$basePaths] : $basePaths;
    }

    /**
     * Parse the given base path for all PHP files and extract documentation information.
     *
     * @return void
     */
    public function parse()
    {
        // Create parser and node finder
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder;

        foreach ($this->getPaths() as $file) {
            // Parse PHP file
            try {
                $parsed = $parser->parse(file_get_contents($file));
            } catch (Error $error) {
                $this->failedPaths[] = [
                    'path' => $file,
                    'error' => $error->getMessage()
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
            $uses = $this->parseUseCases($namespace, $singleUses, $groupedUses);

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
            } else if (count($objects) > 1) {
                $this->failedPaths[] = [
                    'path' => $file,
                    'error' => 'More than one object definition exists in this path.',
                ];
                continue;
            }

            // Parse the objects
            switch (get_class($objects[0])) {
                case \PhpParser\Node\Stmt\Class_::class:
                    $class = $this->parseClassNode($objects[0], $namespace, $uses);
                    break;
                case \PhpParser\Node\Stmt\Trait_::class:
                    $class = $this->parseTraitNode($objects[0], $namespace, $uses);
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

        foreach ($this->basePaths as $basePath) {
            $ds = new FileDatasource(
                $basePath,
                new Filesystem()
            );

            $paths = array_merge($paths, array_map(function ($path) use ($basePath) {
                return $basePath . '/' . $path['fileName'];
            }, $ds->select('/', [
                'extensions' => ['php']
            ])));
        }

        return $this->paths = $paths;
    }

    /**
     * Get all namespaces.
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
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * Parses the "use" cases found in the PHP class.
     *
     * @param string $namespace
     * @param array $singleUses
     * @param array $groupedUses
     * @return array
     */
    protected function parseUseCases(string $namespace, array $singleUses = [], array $groupedUses = [])
    {
        $uses = [];

        // Process single uses
        if (count($singleUses)) {
            foreach ($singleUses as $use) {
                $name = (string) $use->uses[0]->name;
                $fqClass = $this->resolveName($use->uses[0]->name, $namespace, []);

                $alias = (!is_null($use->uses[0]->alias))
                    ? $use->uses[0]->alias->name
                    : null;

                $uses[$alias ?? $name] = [
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

                    $alias = (!is_null($use->alias))
                        ? $use->alias->name
                        : null;

                    $uses[$alias ?? $name] = [
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
     * @return array
     */
    protected function parseClassNode(\PhpParser\Node\Stmt\Class_ $class, string $namespace, array $uses = [])
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

        return [
            'name' => $name,
            'type' => 'class',
            'class' => $fqClass,
            'extends' => $extends,
            'implements' => $implements,
            'traits' => $this->parseClassTraits($class, $namespace, $uses),
            'docs' => $docs,
            'final' => $class->isFinal(),
            'abstract' => $class->isAbstract(),
            'constants' => $this->parseClassConstants($class, $namespace, $uses),
            'properties' => $this->parseClassProperties($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
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
            'type' => 'interface',
            'class' => $fqClass,
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
    protected function parseTraitNode(\PhpParser\Node\Stmt\Trait_ $class, string $namespace, array $uses = [])
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
            'type' => 'trait',
            'class' => $fqClass,
            'docs' => $docs,
            'constants' => $this->parseClassConstants($class, $namespace, $uses),
            'properties' => $this->parseClassProperties($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
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
        return array_map(function ($constant) use ($namespace, $uses) {
            return [
                'name' => (string) $constant->consts[0]->name,
                'type' => $this->normaliseType(gettype($constant->consts[0]->value->value)),
                'value' => (string) json_encode($constant->consts[0]->value->value),
                'docs' => $this->parseDocBlock($constant->getDocComment(), $namespace, $uses)
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
        return array_map(function ($property) use ($namespace, $uses) {
            return [
                'name' => (string) $property->props[0]->name,
                'static' => $property->isStatic(),
                'type' => $this->normaliseType($this->getPropertyType($property, $namespace, $uses)),
                'visibility' => ($property->isPublic())
                    ? 'public'
                    : (($property->isProtected()) ? 'protected' : 'private'),
                'docs' => $this->parseDocBlock($property->getDocComment(), $namespace, $uses)
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
        return array_map(function ($method) use ($namespace, $uses) {
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
                'params' => $this->processMethodParams($method, $namespace, $uses),
            ];
        }, $class->getMethods());
    }

    protected function processMethodParams(FunctionLike $method, string $namespace, array $uses = [])
    {
        return array_map(function ($param) use ($namespace, $uses) {
            print_r($param);
            die();
        }, $method->getParams());
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
                $details['authors'][] = [
                    'name' => $tag->getAuthorName(),
                    'email' => $tag->getEmail(),
                ];
            }
        }

        // Find vars
        if (count($docBlock->getTagsByName('var'))) {
            $var = $docBlock->getTagsByName('var')[0];

            if (empty($details['summary']) && !empty($var->getDescription())) {
                $details['summary'] = Markdown::parse($var->getDescription()->render());
            }

            $details['var'] = [
                'type' => $this->getDocType($var->getType(), $namespace, $uses),
            ];
        }

        return array_filter($details, function ($item, $key) {
            if (in_array($key, ['summary', 'body'])) {
                return !empty(trim($item));
            }
            return !is_null($item);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Extract the type of property or variable from the documentation.
     *
     * For single types, this will return a string. For multiple types, this will return an array.
     *
     * @param Type $class
     * @param string $namespace
     * @param array $uses
     * @return array|string
     */
    protected function getDocType(Type $type, string $namespace, array $uses = [])
    {
        // Handle compound types
        if ($type instanceof Compound) {
            $types = [];

            foreach ($type as $item) {
                if ($item instanceof Object_) {
                    $types[] = $this->resolveName($item->getFqsen()->getName(), $namespace, $uses);
                } else {
                    $types[] = $this->normaliseType($this->resolveName((string) $item, $namespace, $uses));
                }
            }

            return $types;
        }

        if ($type instanceof Object_) {
            return $this->resolveName($type->getFqsen()->getName(), $namespace, $uses);
        } else {
            return $this->normaliseType($this->resolveName((string) $type, $namespace, $uses));
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

        if (method_exists($name, 'isQualified') && !$name->isQualified() && (is_null($alias))) {
            return $namespace . '\\' . (string) $name;
        } else if (!is_null($alias)) {
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
            return $this->normaliseType($this->resolveName($property->type, $namespace, $uses));
        }

        if (!is_null($property->props[0]->default)) {
            if ($property->props[0]->default instanceof \PhpParser\Node\Expr\Array_) {
                return 'array';
            }

            return $this->normaliseType(gettype($property->props[0]->default));
        }

        $docs = $this->parseDocBlock($property->getDocComment(), $namespace, $uses);

        if (!empty($docs['var'])) {
            return $docs['var']['type'];
        }

        return 'mixed';
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
     * @return array|string
     */
    protected function getReturnType(FunctionLike $method, string $namespace, array $uses = [])
    {
        if (!is_null($method->returnType)) {
            return $this->normaliseType($this->resolveName($method->returnType, $namespace, $uses));
        }

        $docs = $this->parseDocBlock($method->getDocComment(), $namespace, $uses);

        if (!empty($docs['return'])) {
            return $docs['return']['type'];
        }

        return 'mixed';
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

        return in_array((string) $name, $scalars);
    }

    /**
     * Normalise type names.
     *
     * Really, this is just for "int" vs. "integer".
     *
     * @param string $type
     * @return string
     */
    protected function normaliseType(string $type)
    {
        switch ($type) {
            case 'int':
                $type = 'integer';
                break;
        }

        return $type;
    }

    /**
     * Processes the inherited properties, constants and methods of each class.
     *
     * @return void
     */
    protected function processInheritance()
    {
        foreach ($this->classes as $name => &$class)
        {
            if ($class['type'] === 'interface' || $class['type'] === 'trait') {
                // Traits and interfaces do not inherit anything
                continue;
            }

            // Set initial inheritance
            $class['inherited'] = [
                'properties' => [],
                'constants' => [],
                'methods' => [],
            ];

            if (
                is_null($class['extends'])
                && !count($class['traits'])
                && !count($class['implements'])
            ) {
                // No inheritance for this class
                continue;
            }

            if (!is_null($class['extends']) && isset($this->classes[$class['extends']])) {
                $this->processSingleInheritance($class, $this->classes[$class['extends']]);
            }

            if (count($class['traits'])) {
                foreach ($class['traits'] as $trait) {
                    if (isset($this->classes[$trait])) {
                        $this->processSingleInheritance($class, $this->classes[$trait]);
                    }
                }
            }

            if (count($class['implements'])) {
                foreach ($class['implements'] as $implements) {
                    if (isset($this->classes[$implements])) {
                        $this->processSingleInheritance($class, $this->classes[$implements]);
                    }
                }
            }
        }
    }

    protected function processSingleInheritance(array &$child, array $ancestor)
    {
        // Compare methods, constants and properties of the parent and inherit anything not overwritten by the child
        // (or already inherited)
        $childMethods = array_merge(
            array_map(function ($method) {
                return $method['name'];
            }, $child['methods'] ?? []),
            array_map(function ($method) {
                return $method['method']['name'];
            }, $child['inherited']['methods'])
        );
        $ancestorMethods = array_map(function ($method) {
            return $method['name'];
        }, $ancestor['methods'] ?? []);

        $inheritedMethods = array_diff($ancestorMethods, $childMethods);

        if (count($inheritedMethods)) {
            foreach ($inheritedMethods as $method) {
                $child['inherited']['methods'][] = [
                    'class' => $ancestor['class'],
                    'method' => array_first($ancestor['methods'], function ($ancestorMethod) use ($method) {
                        return $ancestorMethod['name'] === $method;
                    }),
                ];
            }
        }

        $childProps = array_merge(
            array_map(function ($property) {
                return $property['name'];
            }, $child['properties'] ?? []),
            array_map(function ($property) {
                return $property['property']['name'];
            }, $child['inherited']['properties'])
        );
        $ancestorProps = array_map(function ($property) {
            return $property['name'];
        }, $ancestor['properties'] ?? []);

        $inheritedProps = array_diff($ancestorProps, $childProps);

        if (count($inheritedProps)) {
            foreach ($inheritedProps as $property) {
                $child['inherited']['properties'][] = [
                    'class' => $ancestor['class'],
                    'property' => array_first($ancestor['properties'], function ($ancestorProp) use ($property) {
                        return $ancestorProp['name'] === $property;
                    }),
                ];
            }
        }

        $childConsts = array_merge(
            array_map(function ($constant) {
                return $constant['name'];
            }, $child['constants'] ?? []),
            array_map(function ($constant) {
                return $constant['constant']['name'];
            }, $child['inherited']['constants'])
        );
        $ancestorConsts = array_map(function ($constant) {
            return $constant['name'];
        }, $ancestor['constants'] ?? []);

        $inheritedConsts = array_diff($ancestorConsts, $childConsts);

        if (count($inheritedConsts)) {
            foreach ($inheritedConsts as $constant) {
                $child['inherited']['constants'][] = [
                    'class' => $ancestor['class'],
                    'constant' => array_first($ancestor['constants'], function ($ancestorConst) use ($constant) {
                        return $ancestorConst['name'] === $constant;
                    }),
                ];
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
            if (!is_null($ancestor['extends']) && isset($this->classes[$ancestor['extends']])) {
                $this->processSingleInheritance($child, $this->classes[$ancestor['extends']]);
            }

            if (count($ancestor['traits'])) {
                foreach ($ancestor['traits'] as $trait) {
                    if (isset($this->classes[$trait])) {
                        $this->processSingleInheritance($child, $this->classes[$trait]);
                    }
                }
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
}
