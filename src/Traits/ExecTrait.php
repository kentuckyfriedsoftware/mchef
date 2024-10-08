<?php

namespace App\Traits;

use App\Exceptions\ExecFailed;

trait ExecTrait {
    protected bool $verbose = false;

    /**
     * Allows for {{output}} token to interpolate output from cli failure into error message string.
     *
     * @param string $errorMsg
     * @param null|string|array $output
     * @return string
     */
    private function processErrorMsg(string $errorMsg, null | string | array $output): string {
        if ($output === null) {
            return $errorMsg;
        }
        $useOutput = is_array($output) ? implode("\n", $output) : $output;
        $pattern = '/\{\{(?:\s+|)output(?:\s+|)\}\}/';
        return preg_replace($pattern, $useOutput, $errorMsg);
    }

    protected function exec(string $cmd, ?string $errorMsg = null): string {
        if ($this->verbose && !empty($this->cli)) {
            $this->cli->info($cmd);
        }
        exec($cmd, $output, $returnVar);

        if ($returnVar != 0) {
            throw new ExecFailed(($errorMsg ? $this->processErrorMsg($errorMsg, $output) : "Exec failed : $cmd"), 0, $cmd);
        }

        return implode("\n", $output);
    }

    protected function execStream(string $cmd, ?string $errorMsg = null): string {
        if ($this->verbose && !empty($this->cli)) {
            $this->cli->info($cmd);
        }
        $outputBuffering = ini_get('output_buffering');
        ini_set('output_buffering', 0);
        flush();
        $output = system($cmd, $returnVar);
        if ($returnVar != 0) {
            // Restore output buffering.
            ini_set('output_buffering', $outputBuffering);
            throw new ExecFailed(($errorMsg ? $this->processErrorMsg($errorMsg, $output) : "Exec failed"), 0, $cmd);
        }

        // Restore output buffering.
        ini_set('output_buffering', $outputBuffering);
        return $output;
    }

    protected function execPassthru(string $cmd, ?string $errorMsg = null): void {
        if ($this->verbose && !empty($this->cli)) {
            $this->cli->info($cmd);
        }
        $outputBuffering = ini_get('output_buffering');
        ini_set('output_buffering', 0);
        flush();
        $output = passthru($cmd, $returnVar);
        if ($returnVar != 0) {
            // Restore output buffering.
            ini_set('output_buffering', $outputBuffering);
            throw new ExecFailed(($errorMsg ? $this->processErrorMsg($errorMsg, $output) : "Exec failed"), 0, $cmd);
        }

        // Restore output buffering.
        ini_set('output_buffering', $outputBuffering);
    }
}
