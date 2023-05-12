<?php

namespace App\Service;

use App\Model\DockerContainer;
use App\Model\DockerNetwork;
use App\Traits\ExecTrait;
use splitbrain\phpcli\Exception;

class Docker extends AbstractService {
    use ExecTrait;

    final public static function instance(): Docker {
        return self::setup_instance();
    }

    private function getTableHeadingPositions(string $table, array $headings): array {
        $lines = explode("\n", trim($table));

        $headingPositions = [];

        // Loop through the headings.
        $prevHeading = null;
        foreach ($headings as $heading) {
            // Find the position of the heading in the first line.
            $pos = strpos($lines[0], $heading);
            if ($pos !== false) {
                $headingPositions[$heading] = (object) ['start' => $pos, 'end' => null];
                if ($prevHeading) {
                    $headingPositions[$prevHeading]->end = $pos - 1;
                }
            }
            $prevHeading = $heading;
        }
        return $headingPositions;
    }

    /**
     * Parse a table of information returned by the docker cli commands.
     *
     * @param array $fieldMappings
     * @param string $table
     * @param callable $createModel
     * @return array
     */
    private function parseTable(array $fieldMappings, string $table, callable $createModel): array {
        $lines = explode("\n", trim($table));
        $headings = array_keys($fieldMappings);
        $headingPositions = $this->getTableHeadingPositions($table, $headings);
        $data = [];
        // Parse the docker ps output.
        foreach (array_slice($lines, 1) as $line) { // Loop through the remaining lines.
            $parsedRow = [];
            foreach ($headings as $heading) {
                $offset = $headingPositions[$heading]->start;
                $length = null;
                if (!empty($headingPositions[$heading]->end)) {
                    $length = $headingPositions[$heading]->end - $headingPositions[$heading]->start;
                }
                $parsedRow[$heading] = trim(substr($line, $offset, $length));
            }

            foreach ($fieldMappings as $field => $alt) {
                if (!isset($parsedRow[$field])) {
                    throw new Exception('Docker ps unexpected output format - expected '.$field.' to be present');
                }
            }
            $modelData = [];
            foreach ($parsedRow as $key => $val) {
                if (!empty($fieldMappings[$key])) {
                    $useProp = $fieldMappings[$key];
                } else {
                    $useProp = strtolower($key);
                }
                $modelData[$useProp] = $val;
            }

            $data[] = $createModel($modelData);
        }

        return $data;
    }

    private function parseContainerTable(string $table): array {
        $dockerFields = [
            'CONTAINER ID' => 'containerId',
            'IMAGE' => null,
            'COMMAND' => null,
            'CREATED' => null,
            'STATUS' => null,
            'PORTS' => null,
            'NAMES' => null
        ];
        return $this->parseTable($dockerFields, $table, function($modelData) {
            return new DockerContainer(...$modelData);
        });
    }

    /**
     * @param string $table
     * @return DockerNetwork[]
     */
    private function parseNetworkTable(string $table): array {
        $dockerFields = [
            'NETWORK ID' => 'networkId',
            'NAME' => 'name',
            'DRIVER' => 'driver',
            'SCOPE' => 'scope'
        ];
        return $this->parseTable($dockerFields, $table, function($modelData) {
            return new DockerNetwork(...$modelData);
        });
    }

    public function networkExists(string $networkName): bool {
        $table = $this->exec('docker network ls');
        $networks = $this->parseNetworkTable($table);
        foreach ($networks as $network) {
            if ($network->name === $networkName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return DockerContainer[]
     * @throws \App\Exceptions\ExecFailed
     */
    public function getDockerPs(): array {
        $output = $this->exec('docker ps');
        return $this->parseContainerTable($output);
    }

    /**
     * @return DockerContainer[]
     * @throws \App\Exceptions\ExecFailed
     */
    public function getDockerContainers($includeStopped = true): array {
        $output = $this->exec('docker container ls'.($includeStopped ? ' -a' : ''));
        return $this->parseContainerTable($output);
    }

    public function stopDockerContainer(string $containerName) {
        $this->exec('docker container stop '.$containerName);
    }

    public function startDockerContainer(string $containerName) {
        $this->exec('docker start '.$containerName);
    }
}
