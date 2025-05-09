<?php

namespace Totocsa\MigrationHelper;

use Illuminate\Support\Facades\DB;

class MigrationHelper
{
    public static function defaultCreatedUpdated(string $tableName)
    {
        $driver = DB::getDriverName();

        if ($driver = 'pgsql') {
            DB::statement("ALTER TABLE $tableName ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP");
            DB::statement(
                "CREATE OR REPLACE TRIGGER {$tableName}_set_update_at"
                    . " BEFORE UPDATE"
                    . " ON public.{$tableName}"
                    . " FOR EACH ROW"
                    . " EXECUTE FUNCTION public.set_updated_at()"
            );
        }
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
