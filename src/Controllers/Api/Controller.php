<?php

namespace Niktux\DDD\Analyzer\Controllers\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Puzzle\Pieces\PathManipulation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Controller
{
    use PathManipulation;

    private const REPORT_FILE = 'report.json';

    private
        $varPath;

    public function __construct(string $varPath)
    {
        $this->varPath = $varPath;
    }

    public function reportAction(): JsonResponse
    {
        $reportFile = $this->computeReportFilePath();

        try
        {
            $this->ensureFileExists($reportFile);
            $content = $this->retrieveContent($reportFile);

            return $this->response($content, JsonResponse::HTTP_OK);
        }
        catch(NotFoundHttpException $exception)
        {
            return $this->response(
                [
                    "error" => $exception->getMessage(),
                ],
                JsonResponse::HTTP_NOT_FOUND
            );
        }
        catch(\Exception $exception)
        {
            return $this->response(
                [
                    "error" => $exception->getMessage(),
                ],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function computeReportFilePath(): string
    {
        return $this->enforceEndingSlash($this->varPath) . self::REPORT_FILE;
    }

    private function ensureFileExists(string $path): void
    {
        if(!file_exists($path))
        {
            throw new NotFoundHttpException("Unable to find file at path $path");
        }
    }

    private function retrieveContent(string $path): array
    {
        $content = file_get_contents($path);
        if($content === false)
        {
            throw new \Exception("Unable to get content of $path file");
        }

        $decodedJson = json_decode($content, true);
        if($decodedJson === null)
        {
            throw new \Exception("Unable to decode JSON content of $path file");
        }

        return $decodedJson;
    }

    private function response(?array $content, int $httpReturnCode): JsonResponse
    {
        return new JsonResponse($content, $httpReturnCode);
    }
}
