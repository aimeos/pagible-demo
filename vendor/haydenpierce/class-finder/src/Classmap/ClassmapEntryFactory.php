<?php

namespace HaydenPierce\ClassFinder\Classmap;

use HaydenPierce\ClassFinder\AppConfig;

class ClassmapEntryFactory
{
    /** @var AppConfig */
    private $appConfig;

    /** @var array{0: string, 1: ClassmapEntry[]}|null */
    private $cachedEntries = null;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    /**
     * @return ClassmapEntry[]
     */
    public function getClassmapEntries()
    {
        $appRoot = $this->appConfig->getAppRoot();
        if ($this->cachedEntries !== null && $this->cachedEntries[0] === $appRoot) {
            return $this->cachedEntries[1];
        }

        // Composer will compile user declared mappings to autoload_classmap.php. So no additional work is needed
        // to fetch user provided entries.
        $classmap = require($appRoot . 'vendor/composer/autoload_classmap.php');

        // if classmap has no entries return empty array
        if(count($classmap) == 0) {
            $this->cachedEntries = array($appRoot, array());
            return $this->cachedEntries[1];
        }

        $classmapKeys = array_keys($classmap);
        $entries = array_map(function($index) use ($classmapKeys){
            return new ClassmapEntry($classmapKeys[$index]);
        }, range(0, count($classmap) - 1));

        $this->cachedEntries = array($appRoot, $entries);
        return $entries;
    }
}
