<?php namespace Winter\Docs\Classes;

use Markdown;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Object_;
use PhpParser\Error;
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
                'type' => gettype($constant->consts[0]->value->value),
                'value' => $constant->consts[0]->value->value,
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
                'type' => $this->getPropertyType($property, $namespace, $uses),
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
            return [
                'name' => (string) $method->name,
                'final' => $method->isFinal(),
                'static' => $method->isStatic(),
                'abstract' => $method->isAbstract(),
                'visibility' => ($method->isPublic())
                    ? 'public'
                    : (($method->isProtected()) ? 'protected' : 'private'),
                    'docs' => $this->parseDocBlock($method->getDocComment(), $namespace, $uses)
            ];
        }, $class->getMethods());
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
                    $types[] = $this->resolveName((string) $item, $namespace, $uses);
                }
            }

            return $types;
        }

        if ($type instanceof Object_) {
            return $this->resolveName($type->getFqsen()->getName(), $namespace, $uses);
        } else {
            return $this->resolveName((string) $type, $namespace, $uses);
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
     * @param Property $property
     * @param string $namespace
     * @param array $uses
     * @return array|string
     */
    protected function getPropertyType(Property $property, string $namespace, array $uses = [])
    {
        if (!is_null($property->type)) {
            return $this->resolveName($property->type, $namespace, $uses);
        }

        $docs = $this->parseDocBlock($property->getDocComment(), $namespace, $uses);

        if (!empty($docs['var'])) {
            return $docs['var']['type'];
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
     * Processes the inherited properties, constants and methods of each class.
     *
     * @return void
     */
    protected function processInheritance()
    {
        foreach ($this->classes as $name => $class)
        {

        }
    }
}
