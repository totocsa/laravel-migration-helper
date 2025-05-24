<?php

namespace Totocsa\MigrationHelper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrationHelper
{
    public static function upDefaultCreated(string $tableName)
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE \"$tableName\" ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE `$tableName` CHANGE `created_at` `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;");
        }
    }

    public static function downDefaultCreated(string $tableName)
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE IF EXISTS \"$tableName\" ALTER COLUMN created_at DROP DEFAULT");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE `$tableName` CHANGE `created_at` `created_at` TIMESTAMP NULL");
        }
    }

    public static function upDefaultCreatedUpdated(string $tableName)
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE \"$tableName\" ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP");

            DB::statement(
                "CREATE OR REPLACE TRIGGER \"{$tableName}_set_update_at\""
                    . " BEFORE UPDATE"
                    . " ON public.\"{$tableName}\""
                    . " FOR EACH ROW"
                    . " EXECUTE FUNCTION public.set_updated_at()"
            );
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE `$tableName` CHANGE `created_at` `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;");

            DB::statement("DROP TRIGGER IF EXISTS `{$tableName}_set_updated_at`");
            DB::statement(
                "CREATE TRIGGER `{$tableName}_set_updated_at` BEFORE UPDATE ON `{$tableName}` FOR EACH ROW BEGIN\n"
                    . "IF NEW.`updated_at` IS NULL THEN\n"
                    . "  SET NEW.`updated_at` = CURRENT_TIMESTAMP;\n"
                    . "END IF;\n"
                    . "END\n"
            );
        }
    }

    public static function downDefaultCreatedUpdated(string $tableName)
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE IF EXISTS \"$tableName\" ALTER COLUMN created_at DROP DEFAULT");
            DB::statement("DROP TRIGGER IF EXISTS \"{$tableName}_set_updated_at\" ON \"$tableName\"");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE `$tableName` CHANGE `created_at` `created_at` TIMESTAMP NULL");
            DB::statement("DROP TRIGGER IF EXISTS `{$tableName}_set_updated_at`");
        }
    }

    public static function stubsToMigrations($groups, $path)
    {
        $global = $GLOBALS;
        $isPublish = isset($global['argv']) && isset($global['argv'][0]) && $global['argv'][0] === 'artisan'
            && isset($global['argv'][1]) && $global['argv'][1] === 'vendor:publish'
            && isset($global['argv'][2]) && $global['argv'][2] === "--tag=$groups";

        $files = File::files($path);
        $sortedFiles = collect($files)->sortBy(fn($file) => $file->getFilename())->values();

        $paths = [];
        foreach ($sortedFiles as $v) {
            $fileInfo = $v->getFileInfo();
            $publishAs = self::publishAs($v, $isPublish, 2);

            $paths[$fileInfo->getPathname()] = "$publishAs";
        }

        return $paths;
    }

    public static function publishedAs($fileinfo)
    {
        $iterator = new \RecursiveDirectoryIterator(database_path('migrations'), \FilesystemIterator::CURRENT_AS_FILEINFO);
        $iterator->rewind();

        $publishedAs = false;
        while ($publishedAs === false && $iterator->valid()) {
            if ($iterator->isFile() && $fileinfo->getSize() === $iterator->getSize()) {
                $fileinfoContent = file_get_contents($fileinfo->getPathname());
                $iteratorContent = file_get_contents($iterator->getPathname());

                $publishedAs = $fileinfoContent === $iteratorContent ? $iterator->getPathname() : false;
            }

            $iterator->next();
        }

        return $publishedAs;
    }

    public static function publishAs($fileInfo, $isPublish, $sleep)
    {
        $publishedAs = self::publishedAs($fileInfo);
        if ($publishedAs === false) {
            if ($isPublish) {
                sleep($sleep);
            }

            $targetName = substr($fileInfo->getFilename(), 0, -5);
            $targetArray = explode('_', $targetName);
            array_shift($targetArray);

            $publishedAs = database_path('migrations') . DIRECTORY_SEPARATOR . date('Y_m_d_His_') . implode('_', $targetArray);
        }

        return $publishedAs;
    }
}
