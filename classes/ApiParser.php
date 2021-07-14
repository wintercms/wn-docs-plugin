<?php namespace Winter\Docs\Classes;

use Markdown;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Error;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Winter\Storm\Filesystem\Filesystem;
use Winter\Storm\Halcyon\Datasource\FileDatasource;

class ApiParser
{
    protected $basePath;

    protected $paths = [];

    protected $namespaces = [];

    protected $classes = [];

    protected $failedPaths = [];

    /** @var DocBlockFactory Factory instance for generating DocBlock reflections */
    protected $docBlockFactory;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function parse()
    {
        // Create parser and node finder
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder;

        foreach ($this->getPaths() as $file) {
            // Parse PHP file
            try {
                $parsed = $parser->parse($file['content']);
            } catch (Error $error) {
                $this->failedPaths[] = [
                    'path' => $file['fileName'],
                    'error' => $error->getMessage()
                ];
                continue;
            }

            // Find namespace
            $namespace = $nodeFinder->findFirstInstanceOf($parsed, \PhpParser\Node\Stmt\Namespace_::class);
            if (is_null($namespace)) {
                $namespace = '__GLOBAL__';
            } else {
                $namespace = $namespace->name;
            }
            if (!in_array($namespace, $this->namespaces)) {
                $this->namespaces[] = $namespace;
            }

            // Find use cases
            $singleUses = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Use_::class);
            $groupedUses = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\GroupUse::class);
            $uses = $this->parseUseCases($namespace, $singleUses, $groupedUses);

            // Ensure that we are dealing with a single class
            $classes = $nodeFinder->findInstanceOf($parsed, \PhpParser\Node\Stmt\Class_::class);

            if (!count($classes)) {
                $this->failedPaths[] = [
                    'path' => $file['fileName'],
                    'error' => 'No class definition found.',
                ];
                continue;
            } else if (count($classes) > 1) {
                $this->failedPaths[] = [
                    'path' => $file['fileName'],
                    'error' => 'More than one class definition exists in this path.',
                ];
                continue;
            }

            $class = $this->parseClassNode($namespace, $uses, $classes[0]);
            $class['path'] = $file['fileName'];
            $this->classes[$class['class']] = $class;
        }
    }

    public function getPaths(bool $force = false)
    {
        // Return cached paths
        if (count($this->paths) && !$force) {
            return $this->paths;
        }

        $ds = new FileDatasource(
            $this->basePath,
            new Filesystem()
        );

        return $this->paths = $ds->select('/', [
            'columns' => 'fileName',
            'extensions' => ['php']
        ]);
    }

    public function getNamespaces()
    {
        return $this->namespaces;
    }

    public function getClasses()
    {
        return $this->classes;
    }

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

    protected function parseClassNode(string $namespace, array $uses = [], \PhpParser\Node\Stmt\Class_ $class)
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
            $docs = $this->parseDocBlock($class->getDocComment());
        } else {
            $docs = null;
        }

        return [
            'name' => $name,
            'class' => $fqClass,
            'extends' => $extends,
            'implements' => $implements,
            'docs' => $docs,
            'constants' => $this->parseClassConstants($class),
            'properties' => $this->parseClassProperties($class, $namespace, $uses),
            'methods' => $this->parseClassMethods($class, $namespace, $uses),
        ];
    }

    protected function parseClassConstants(\PhpParser\Node\Stmt\Class_ $class)
    {
        return array_map(function ($constant) {
            return [
                'name' => (string) $constant->name,
            ];
        }, $class->getConstants());
    }

    protected function parseClassProperties(\PhpParser\Node\Stmt\Class_ $class, string $namespace, array $uses = [])
    {
        return array_map(function ($property) use ($namespace, $uses) {
            print_r($property);
            $details = [
                'name' => (string) $property->props[0]->name,
                'static' => $property->isStatic(),
                'type' => $this->getPropertyType($property, $namespace, $uses),
                'visibility' => ($property->isPublic())
                    ? 'public'
                    : (($property->isProtected()) ? 'protected' : 'private'),
                'docs' => $this->parseDocBlock($property->getDocComment())
            ];
        }, $class->getProperties());
    }

    protected function parseClassMethods(\PhpParser\Node\Stmt\Class_ $class, string $namespace, array $uses = [])
    {
        return array_map(function ($methods) {

        }, $class->getMethods());
    }

    protected function parseDocBlock(string $comment)
    {
        if (!isset($this->docBlockFactory)) {
            $this->docBlockFactory = DocBlockFactory::createInstance();
        }

        $docBlock = $this->docBlockFactory->create($comment);

        $details = [
            'summary' => $docBlock->getSummary(),
            'body' => Markdown::parse($docBlock->getDescription()->render()),
            'since' => (count($docBlock->getTagsByName('since')))
                ? $docBlock->getTagsByName('since')[0]->getVersion()
                : null,
            'deprecated' => (count($docBlock->getTagsByName('deprecated')))
                ? $docBlock->getTagsByName('deprecated')[0]->getVersion()
                : null,
        ];

        // Find authors
        if (count($docBlock->getTagsByName('author'))) {
            /** @var phpDocumentor\Reflection\DocBlock\Tags\Author */
            foreach ($docBlock->getTagsByName('author') as $tag) {
                $details['authors'][] = [
                    'name' => $tag->getAuthorName(),
                    'email' => $tag->getEmail(),
                ];
            }
        }

        return $details;
    }

    protected function resolveName(Name $name, string $namespace, array $uses = [], $alias = null)
    {
        // If this name is part of a use case, use that as the name
        if (array_key_exists((string) $name, $uses)) {
            return $uses[(string) $name]['class'];
        }

        if (!$name->isQualified() && (is_null($alias))) {
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

    protected function getPropertyType(Property $property)
    {
        if (!is_null($property->type)) {
            return $this->resolveName($property->type);
        }
    }
}
