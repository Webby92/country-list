<?php

namespace Sasedev\Composer\Plugin\Filescopier;

use Composer\IO\IOInterface;
use Composer\Composer;
use Composer\Script\Event;

/**
 *
 * @author sasedev <seif.salah@gmail.com>
 */
class Processor
{

    /**
     *
     * @var IOInterface
     */
    private $io;

    /**
     *
     * @var Composer $composer
     */
    private $composer;

    private $originalSource;
    private $originalDest;
    private $extensions;

    public function __construct(Event $ev)
    {
        $this->io = $ev->getIO();
        $this->composer = $ev->getComposer();
    }

    public function processCopy(array $config)
    {
		$config = $this->processConfig($config);
		$debug = $config['debug'];
		if ($debug)
			error_reporting(E_ALL);

        $project_path = realpath(realpath($this->composer->getConfig()->get('vendor-dir').'/../').'/');
        if ($debug)
            $this->io->write('[sasedev/composer-plugin-filecopier] basepath : '.$project_path);

        $this->originalSource = $config['source'];
        $this->originalDest = $config['destination'];
        $this->extensions = $config['extension'];

        $source =  realpath($project_path ."/" . preg_replace('/\*.*$/', '', $this->originalSource));
        $destination =   $this->GetAbsolutePath($project_path ."/" .  preg_replace('/\*.*$/', '', $this->originalDest));

        if ($debug) {
            $this->io->write('[sasedev/composer-plugin-filecopier] init source : ' . $source);
            $this->io->write('[sasedev/composer-plugin-filecopier] init destination : ' . $destination);
        }

        if ($config['createDestination'] === true)
        {
            if (!file_exists($destination) || (file_exists($destination) && is_file($destination))) 
            {
				if (!mkdir($destination, 0755, true))
				{
					$this->io->write('[sasedev/composer-plugin-filecopier] New Folder Creation FAILED!'. $destination);
					return true;
				}
				else
				{
					if ($debug)
                    	$this->io->write('[sasedev/composer-plugin-filecopier] New Folder '. $destination);
				}
            }
		}
		
        $sources = \glob($source, GLOB_MARK);
        if (!empty($sources)) 
        {
            foreach ($sources as $newsource)
                $this->copyr($newsource, $destination, true, $debug);
        }
    }

    private function processConfig(array $config)
    {
        if (empty($config['source'])) {
            throw new \InvalidArgumentException('The extra.filescopier.source setting is required to use this script handler.');
        }

        if (empty($config['destination'])) {
            throw new \InvalidArgumentException('The extra.filescopier.destination setting is required to use this script handler.');
        }

        if (empty($config['debug']) || $config['debug'] != 'true') {
            $config['debug'] = false;
        } else {
            $config['debug'] = true;
        }
        
        if (empty($config['createDestination']) || !is_bool($config['createDestination']))
            $config['createDestination'] = false;

        if (empty($config['extension']))
            $config['extension'] = array();
        else
        {
            $config['extension'] = array_map('strtolower', explode('|', $config['extension']));
        }
        return $config;
    }

    private function copyr($source, $destination, $isRoot, $debug = false)
    {        
        $originalSource = $source;
        $originalDest = $destination;

        $source = realpath($source);
        $destination = $this->GetAbsolutePath($destination . '/');

        if (false === $source || empty($source)) {
            if ($debug) 
                $this->io->write('[sasedev/composer-plugin-filecopier] No copy : source (' . $originalSource . ') not valid!');
            return true;
        }

        if (empty($destination)) {
            if ($debug) 
                $this->io->write('[sasedev/composer-plugin-filecopier] No copy : dest (' . $originalDest . ') not valid!');
            return true;
        }

        if ($source === $destination) {
            if ($debug)
                $this->io->write('[sasedev/composer-plugin-filecopier] No copy : source ('.$source.') and destination ('.$destination.') are identicals');
            return true;
        }
        
        // Check for symlinks
        if (\is_link($source)) {
            if ($debug) {
                $this->io->write('[sasedev/composer-plugin-filecopier] Copying Symlink '.source.' to '.$destination);
            }
            $source_entry = \basename($source);
            if (!$debug) 
                return \symlink(\readlink($source), $destination.'/'.$source_entry);
            return true;
        }

        if (\is_dir($source)) 
        {
            if ($debug) 
                $this->io->write('[sasedev/composer-plugin-filecopier] Scanning Folder '. $source);

            // Loop through the folder
            if (!$isRoot)
            {
                $source_entry = \basename($source);
                if (!\is_dir($destination)) 
                {
					if (!\is_dir($destination)) 
					{
						if (!file_exists($destination) || (file_exists($destination) && is_file($destination))) 
						{
							$this->io->write('[sasedev/composer-plugin-filecopier] New Folder Creation FAILED!'. $destination);
							return true;
						}
						else
						{
							if ($debug)
								$this->io->write('[sasedev/composer-plugin-filecopier] New Folder '. $destination);
						}
					}
                }
                $destination =  $this->GetAbsolutePath($destination.'/'.$source_entry.'/');
            }
            else
            {
                if ($debug)
                    $this->io->write('[sasedev/composer-plugin-filecopier] Root not created Folder '. $destination);
            }

            $dir = \dir($source);
            while (false !== $entry = $dir->read()) {
                // Skip pointers
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                // Deep copy directories
                $sourcePath = realpath($source.'/'.$entry);
                if ($sourcePath !== false)
                    $this->copyr($sourcePath, $destination, false, $debug);
            }

            // Clean up
            $dir->close();
            return true;
        }

        if (\is_file($source)) 
        {
            $source_entry = basename($source);
            $destinationFolder = $destination;
            if (is_dir($destination))
            {
                $destination = $this->GetAbsolutePath($destination.'/'.$source_entry);
            }
            else
            {
                $this->io->write('[sasedev/composer-plugin-filecopier] Destination is not a dir. Dest: '.$destination);
                return true;
            }

            $pathInfo = pathinfo($source);
            if (empty($this->extensions) || in_array(strtolower($pathInfo['extension']), $this->extensions))
            {
                if (!file_exists($destinationFolder)) 
                {
                    if ($debug)
                        $this->io->write('[sasedev/composer-plugin-filecopier] New Folder '. $destinationFolder);
                    mkdir($destination, 0755, true);
                }
                
                if ($debug)
                     $this->io->write('[sasedev/composer-plugin-filecopier] Copying File '.$source.' to '.$destination);
                return \copy($source, $destination);
            }
            else if ($debug)
                $this->io->write('[sasedev/composer-plugin-filecopier] Invalid file extension: ' . $source);
        }
        return true;
    }

    /**
     * Check if a string starts with a prefix
     *
     * @param string $string
     * @param string $prefix
     *
     * @return boolean
     */
    private function startsWith($string, $prefix) {
        return $prefix === "" || strrpos($string, $prefix, -strlen($string)) !== FALSE;
    }

    /**
     * Check if a string ends with a suffix
     *
     * @param string $string
     * @param string $suffix
     *
     * @return boolean
     */
    private function endswith($string, $suffix)
    {
        $strlen = strlen($string);
        $testlen = strlen($suffix);
        if ($testlen > $strlen) {
            return false;
        }

        return substr_compare($string, $suffix, -$testlen) === 0;
    }
    
    private function relativePath($from, $to, $separator = DIRECTORY_SEPARATOR)
    {
        $from   = str_replace(array('/', '\\'), $separator, $from);
        $to     = str_replace(array('/', '\\'), $separator, $to);

        $arFrom = explode($separator, rtrim($from, $separator));
        $arTo = explode($separator, rtrim($to, $separator));
        while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0]))
        {
            array_shift($arFrom);
            array_shift($arTo);
        }

        return str_pad("", count($arFrom) * 3, '..'.$separator).implode($separator, $arTo);
    }

    function GetAbsolutePath($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}