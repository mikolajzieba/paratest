<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\MetaProvider;
use SimpleXMLElement;

use function array_merge;
use function array_reduce;
use function call_user_func_array;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function filesize;
use function unlink;

final class Reader extends MetaProvider
{
    /** @var SimpleXMLElement */
    private $xml;

    /** @var bool */
    private $isSingle = false;

    /** @var TestSuite[] */
    private $suites = [];

    /** @var string */
    protected $logFile;

    /** @var array<string, int|string> */
    private static $defaultSuite = [
        'name' => '',
        'file' => '',
        'tests' => 0,
        'assertions' => 0,
        'failures' => 0,
        'errors' => 0,
        'skipped' => 0,
        'time' => 0,
    ];

    public function __construct(string $logFile)
    {
        if (! file_exists($logFile)) {
            throw new InvalidArgumentException("Log file $logFile does not exist");
        }

        $this->logFile = $logFile;
        if (filesize($logFile) === 0) {
            throw new InvalidArgumentException(
                "Log file $logFile is empty. This means a PHPUnit process has crashed."
            );
        }

        $logFileContents = file_get_contents($this->logFile);
        $this->xml       = new SimpleXMLElement($logFileContents);
        $this->init();
    }

    /**
     * Returns whether or not this reader contains only
     * a single suite.
     */
    public function isSingleSuite(): bool
    {
        return $this->isSingle;
    }

    /**
     * Return the Reader's collection
     * of test suites.
     *
     * @return TestSuite[]
     */
    public function getSuites(): array
    {
        return $this->suites;
    }

    /**
     * Return an array that contains
     * each suite's instant feedback. Since
     * logs do not contain skipped or incomplete
     * tests this array will contain any number of the following
     * characters: .,F,E
     * TODO: Update this, skipped was added in phpunit.
     *
     * @return string[]
     */
    public function getFeedback(): array
    {
        $feedback = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            foreach ($suite->cases as $case) {
                if (count($case->failures) > 0) {
                    $feedback[] = 'F';
                } elseif (count($case->errors) > 0) {
                    $feedback[] = 'E';
                } elseif (count($case->skipped) > 0) {
                    $feedback[] = 'S';
                } elseif (count($case->warnings) > 0) {
                    $feedback[] = 'W';
                } else {
                    $feedback[] = '.';
                }
            }
        }

        return $feedback;
    }

    /**
     * Remove the JUnit xml file.
     */
    public function removeLog(): void
    {
        unlink($this->logFile);
    }

    /**
     * Initialize the suite collection
     * from the JUnit xml document.
     */
    private function init(): void
    {
        $this->initSuite();
        $cases = $this->getCaseNodes();
        foreach ($cases as $file => $nodeArray) {
            $this->initSuiteFromCases($nodeArray);
        }
    }

    /**
     * Uses an array of testcase nodes to build a suite.
     *
     * @param SimpleXMLElement[] $nodeArray an array of SimpleXMLElement nodes representing testcase elements
     */
    private function initSuiteFromCases(array $nodeArray): void
    {
        $testCases  = [];
        $properties = $this->caseNodesToSuiteProperties($nodeArray, $testCases);
        if (! $this->isSingle) {
            $this->addSuite($properties, $testCases);
        } else {
            $suite        = $this->suites[0];
            $suite->cases = array_merge($suite->cases, $testCases);
        }
    }

    /**
     * Creates and adds a TestSuite based on the given
     * suite properties and collection of test cases.
     *
     * @param array<string, int|string|float> $properties
     * @param TestCase[]                      $testCases
     */
    private function addSuite(array $properties, array $testCases): void
    {
        $suite                     = TestSuite::suiteFromArray($properties);
        $suite->cases              = $testCases;
        $this->suites[0]->suites[] = $suite;
    }

    /**
     * Fold an array of testcase nodes into a suite array.
     *
     * @param SimpleXMLElement[] $nodeArray an array of testcase nodes
     * @param TestCase[]         $testCases an array reference. Individual testcases will be placed here.
     *
     * @return array<string, int|string>
     */
    private function caseNodesToSuiteProperties(array $nodeArray, array &$testCases = []): array
    {
        $cb = [TestCase::class, 'caseFromNode'];

        return array_reduce(
            $nodeArray,
            static function (array $result, SimpleXMLElement $c) use (&$testCases, $cb): array {
                $testCases[]    = call_user_func_array($cb, [$c]);
                $result['name'] = (string) $c['class'];
                $result['file'] = (string) $c['file'];
                ++$result['tests'];
                $result['assertions'] += (int) $c['assertions'];
                $result['failures']   += count($c->xpath('failure'));
                $result['errors']     += count($c->xpath('error'));
                $result['skipped']    += count($c->xpath('skipped'));
                $result['time']       += (float) $c['time'];

                return $result;
            },
            static::$defaultSuite
        );
    }

    /**
     * Return a collection of testcase nodes
     * from the xml document.
     *
     * @return array<string, SimpleXMLElement[]>
     */
    private function getCaseNodes(): array
    {
        $caseNodes = $this->xml->xpath('//testcase');
        $cases     = [];
        foreach ($caseNodes as $node) {
            $caseFilename = (string) $node['file'];
            if (! isset($cases[$caseFilename])) {
                $cases[$caseFilename] = [];
            }

            $cases[$caseFilename][] = $node;
        }

        return $cases;
    }

    /**
     * Determine if this reader is a single suite
     * and initialize the suite collection with the first
     * suite.
     */
    private function initSuite(): void
    {
        $suiteNodes     = $this->xml->xpath('/testsuites/testsuite/testsuite');
        $this->isSingle = count($suiteNodes) === 0;
        $node           = current($this->xml->xpath('/testsuites/testsuite'));

        if ($node !== false) {
            $this->suites[] = TestSuite::suiteFromNode($node);
        } else {
            $this->suites[] = TestSuite::suiteFromArray(self::$defaultSuite);
        }
    }

    /**
     * Return a value as a float or integer.
     *
     * @return float|int
     */
    protected function getNumericValue(string $property)
    {
        return $property === 'time'
            ? (float) $this->suites[0]->$property
            : (int) $this->suites[0]->$property;
    }

    /**
     * Return messages for a given type.
     *
     * @return string[]
     */
    protected function getMessages(string $type): array
    {
        $messages = [];
        $suites   = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach ($suites as $suite) {
            $messages = array_merge(
                $messages,
                array_reduce($suite->cases, static function ($result, TestCase $case) use ($type): array {
                    return array_merge($result, array_reduce($case->$type, static function ($msgs, $msg): array {
                        $msgs[] = $msg['text'];

                        return $msgs;
                    }, []));
                }, [])
            );
        }

        return $messages;
    }
}
