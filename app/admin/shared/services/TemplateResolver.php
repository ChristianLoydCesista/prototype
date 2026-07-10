<?php

/**
 * TemplateResolver
 *
 * Resolves the correct HTML template for a document request.
 *
 * Resolution priority:
 * 1. Barangay-specific template
 * 2. Document type default template
 * 3. Throw an exception if no valid template exists
 */
class TemplateResolver
{
    private string $projectRoot;
    private string $templatesRoot;

    public function __construct(string $projectRoot)
    {
        $normalizedProjectRoot = realpath($projectRoot);

        if ($normalizedProjectRoot === false) {
            throw new InvalidArgumentException('Invalid project root directory.');
        }

        $this->projectRoot = $this->normalizePath($normalizedProjectRoot);
        $this->templatesRoot = $this->projectRoot . '/templates';

        if (!is_dir($this->templatesRoot)) {
            throw new RuntimeException(
                'Templates directory does not exist: ' . $this->templatesRoot
            );
        }
    }

    /**
     * Resolve a document template.
     *
     * @return array{
     *     path: string,
     *     relative_path: string,
     *     source: string,
     *     template_key: string
     * }
     */
    public function resolve(
        int $barangayId,
        string $templateKey,
        ?string $customTemplateDirectory,
        ?string $defaultTemplatePath
    ): array {
        if ($barangayId <= 0) {
            throw new InvalidArgumentException('Invalid barangay ID.');
        }

        $templateKey = trim($templateKey);

        if (!$this->isValidTemplateKey($templateKey)) {
            throw new InvalidArgumentException(
                'Invalid template key: ' . $templateKey
            );
        }

        $templateFilename = $templateKey . '.html';

        /*
        |--------------------------------------------------------------------------
        | 1. Barangay-specific template
        |--------------------------------------------------------------------------
        */

        $customDirectory = $this->resolveCustomDirectory(
            $barangayId,
            $customTemplateDirectory
        );

        $customCandidate = $customDirectory . '/' . $templateFilename;

        if ($this->isValidTemplateFile($customCandidate)) {
            return $this->buildResult(
                $customCandidate,
                'barangay',
                $templateKey
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Configured default template
        |--------------------------------------------------------------------------
        */

        if (!empty($defaultTemplatePath)) {
            $defaultCandidate = $this->resolveProjectPath(
                $defaultTemplatePath
            );

            if ($this->isValidTemplateFile($defaultCandidate)) {
                return $this->buildResult(
                    $defaultCandidate,
                    'default',
                    $templateKey
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Conventional default template
        |--------------------------------------------------------------------------
        */

        $conventionalDefault = $this->templatesRoot
            . '/default/'
            . $templateFilename;

        if ($this->isValidTemplateFile($conventionalDefault)) {
            return $this->buildResult(
                $conventionalDefault,
                'default',
                $templateKey
            );
        }

        throw new RuntimeException(
            sprintf(
                'No valid template found for "%s" in barangay ID %d.',
                $templateKey,
                $barangayId
            )
        );
    }

    /**
     * Resolve the barangay custom template directory.
     */
    private function resolveCustomDirectory(
        int $barangayId,
        ?string $customTemplateDirectory
    ): string {
        if (!empty($customTemplateDirectory)) {
            $configuredDirectory = $this->resolveProjectPath(
                $customTemplateDirectory
            );

            if ($this->isInsideTemplatesDirectory($configuredDirectory)) {
                return $configuredDirectory;
            }
        }

        return $this->templatesRoot . '/barangays/' . $barangayId;
    }

    /**
     * Convert a relative project path into an absolute path.
     */
    private function resolveProjectPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $path = $this->normalizePath($path);

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    /**
     * Check whether the template exists and stays inside /templates.
     */
    private function isValidTemplateFile(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            return false;
        }

        $realPath = $this->normalizePath($realPath);

        if (!$this->isInsideTemplatesDirectory($realPath)) {
            return false;
        }

        return strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) === 'html';
    }

    /**
     * Check that a path is inside the project's templates directory.
     */
    private function isInsideTemplatesDirectory(string $path): bool
    {
        $templatesRoot = realpath($this->templatesRoot);
        $resolvedPath = realpath($path);

        if ($templatesRoot === false || $resolvedPath === false) {
            return false;
        }

        $templatesRoot = rtrim(
            $this->normalizePath($templatesRoot),
            '/'
        );

        $resolvedPath = $this->normalizePath($resolvedPath);

        return $resolvedPath === $templatesRoot
            || str_starts_with(
                $resolvedPath,
                $templatesRoot . '/'
            );
    }

    /**
     * Template keys may contain lowercase letters, numbers, and hyphens.
     */
    private function isValidTemplateKey(string $templateKey): bool
    {
        return preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $templateKey
        ) === 1;
    }

    /**
     * Check whether a path is absolute on Windows or Unix.
     */
    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_starts_with($path, '/');
    }

    /**
     * Normalize Windows directory separators.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Build a consistent resolver response.
     */
    private function buildResult(
        string $absolutePath,
        string $source,
        string $templateKey
    ): array {
        $realPath = realpath($absolutePath);

        if ($realPath === false) {
            throw new RuntimeException('Resolved template file is unavailable.');
        }

        $realPath = $this->normalizePath($realPath);

        return [
            'path' => $realPath,
            'relative_path' => ltrim(
                str_replace($this->projectRoot, '', $realPath),
                '/'
            ),
            'source' => $source,
            'template_key' => $templateKey,
        ];
    }
}
