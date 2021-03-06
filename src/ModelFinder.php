<?php

namespace BeyondCode\ErdGenerator;

use Illuminate\Support\Str;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt\Class_;
use Illuminate\Support\Collection;
use PhpParser\Node\Stmt\Namespace_;
use Illuminate\Filesystem\Filesystem;
use PhpParser\NodeVisitor\NameResolver;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class ModelFinder
{

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getModelsInDirectory(string $directory): Collection
    {
        $files = config('erd-generator.recursive') ?
            $this->filesystem->allFiles($directory) :
            $this->filesystem->files($directory);

        return Collection::make($files)->filter(function ($path) {
            return Str::endsWith($path, '.php');
        })->map(function ($path) {
            return $this->getFullyQualifiedClassNameFromFile($path);
        })->filter(function (string $className) {
            return !empty($className);
        })->filter(function (string $className) {
            return is_subclass_of($className, EloquentModel::class);
        });
    }

    protected function getFullyQualifiedClassNameFromFile(string $path): string
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $code = file_get_contents($path);

        $statements = $parser->parse($code);
        $statements = $traverser->traverse($statements);

        // get the first namespace declaration in the file
        $root_statement = collect($statements)->filter(function ($statement) {
            return $statement instanceof Namespace_;
        })->first();

        if (! $root_statement) {
            return '';
        }

        return collect($root_statement->stmts)
                ->filter(function ($statement) {
                    return $statement instanceof Class_;
                })
                ->map(function (Class_ $statement) {
                    return $statement->namespacedName->toString();
                })
                ->first() ?? '';
    }
}
