<?php

namespace TinyPixel\PHPDocZ;

use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Illuminate\Support\Collection;

class Generator
{
    /**
     * Output directory.
     *
     * @var string
     */
    protected $outputDir;

    /**
     * The list of classes and interfaces.
     *
     * @var array
     */
    protected $classDefinitions;

    /**
     * Directory containing the twig templates.
     *
     * @var string
     */
    protected $templateDir;

    /**
     * A simple template for generating links.
     *
     * @var string
     */
    protected $linkTemplate;

    /**
     * Filename for API Index.
     *
     * @var string
     */
    protected $apiIndexFile;

    /**
     * Base namespace (will be removed)
     *
     * @var string
     */
    public $baseNamespace = "tinypixel-acorn-database-";

    /**
     * @param array  $classDefinitions
     * @param string $outputDir
     * @param string $templateDir
     * @param string $linkTemplate
     * @param string $apiIndexFile
     */
    public function __construct(array $classDefinitions, $outputDir, $templateDir, $linkTemplate = '%c.md')
    {
        $this->classDefinitions = $this->processClassDefinitions($classDefinitions);
        $this->outputDir = $outputDir;
        $this->templateDir = $templateDir;
        $this->linkTemplate = $linkTemplate;
    }

    /**
     * Process class definitions
     *
     * @param array $classDefinitions
     * @return array
     */
    public function processClassDefinitions($classDefinitions)
    {
        $definitions = Collection::make();

        Collection::make($classDefinitions)->each(function ($classDefinition) use (&$definitions) {
            $lowerFileName = strtolower($classDefinition['fileName']);
            $lowerClassName = strtolower($classDefinition['className']);

            /**
             * New filename is lowercase
             */
            $classDefinition['fileName'] = $lowerFileName . $lowerClassName;

            /**
             * Remove the base namespace for readability
             */
            $classDefinition['fileName'] = str_replace($this->baseNamespace, '', $classDefinition['fileName']);

            /**
             * Replace - with / to form DocZ urls
             */
            $classDefinition['fileName'] = str_replace("-", "/", $classDefinition['fileName']);

            /**
             * Add class to collection
             */
            $definitions->push($classDefinition);
        });

        return $definitions;
    }

    /**
     * create file with content, and create folder structure if doesn't exist
     * @param String $filepath
     * @param String $message
     */
    public function forceFilePutContents($filepath, $message)
    {
        try {
            $isInFolder = preg_match("/^(.*)\/([^\/]+)$/", $filepath, $filepathMatches);

            if ($isInFolder) {
                $folderName = $filepathMatches[1];
                $fileName = $filepathMatches[2];

                if (!is_dir($folderName)) {
                    mkdir($folderName, 0777, true);
                }
            }
            file_put_contents($filepath, $message);
        } catch (Exception $e) {
            echo "ERR: error writing '$message' to '$filepath', " . $e->getMessage();
        }
    }

    /**
     * Runs the generator
     *
     * @todo this is a mess
     */
    public function run()
    {
        $loader = new Twig_Loader_Filesystem($this->templateDir);
        $twig = new Twig_Environment($loader);
        $twig->addFilter(new Twig_SimpleFilter('classLink', ['\\TinyPixel\\PHPDocz\\Generator', 'classLink']));

        $GLOBALS['PHPDocz_classDefinitions'] = $this->classDefinitions;
        $GLOBALS['PHPDocz_linkTemplate'] = $this->linkTemplate;

        foreach ($this->classDefinitions as $className => $data) {
            $data['link'] = str_replace('\\', '/', $data['namespace']);
            $data['link'] = str_replace('TinyPixel/Acorn', '', $data['link']);
            $data['link'] = str_replace('/Database/', '', $data['link']);
            $data['link'] = str_replace('/Database', 'Utility', $data['link']);
            $data['link'] = '/' . strtolower($data['link']) . '/' . strtolower($data['shortClass']);

            $data['parent'] = explode('/', $data['link']);
            $parent = array_pop($data['parent']);

            if (is_array($data['parent'])) {
                $parent = array_pop($data['parent']);
            }

            $data['parent'] = ucfirst($parent);

            $output = $twig->render('class.twig', $data);

            $this->forceFilePutContents("{$this->outputDir}/{$data['link']}.md", $output);
        }
    }

    /**
     * Twig templated links
     *
     * @todo this is a mess.
     */
    public static function classLink($className, $label = null)
    {
        $classDefinitions = $GLOBALS['PHPDocz_classDefinitions'];
        $returnedClasses = [];

        foreach (explode('|', $className) as $singleClass) {
            $singleClass = trim($singleClass, '\\ ');

            if (!isset($classDefinitions[$singleClass])) {
                $returnedClasses[] = $singleClass;
            } else {
                $singleClass = str_replace('\\TinyPixel\\Acorn\\', '\\AcornDB\\', $singleClass);

                $link = strtr($GLOBALS['PHPDocz_linkTemplate'], [
                    '%c' => strtolower("{$singleClass}/{$classDefinitions['shortName']}")
                ]);

                $returnedClasses[] = sprintf("[%s](%s)", $label, $singleClass);
            }
        }

        return implode('|', $returnedClasses);
    }
}
