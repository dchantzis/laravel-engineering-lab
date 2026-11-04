<?php

namespace App\Http\Responses;

use App\Enums\DatabaseConnection;
use Error;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use SodiumException;

class ApiResponse
{
    private Request $request;

    /**
     * @var mixed
     */
    private $content;

    /**
     * @var mixed
     */
    private $meta;

    /**
     * @var int
     */
    private $httpStatus;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var int
     */
    private $options;

    /**
     * @var Exception|Error|null
     */
    private Exception|Error|null $exception;

    public function __construct(
        $content,
        $meta,
        int $httpStatus = Response::HTTP_OK,
        Exception|Error|null $exception = null,
        array $headers = [],
        int $options = 0
    ) {
        $this->request = app(Request::class);
        $this->content = $content;
        $this->meta = $meta;
        $this->httpStatus = $httpStatus;
        $this->headers = $headers;
        $this->options = $options;
        $this->exception = $exception;
    }

    /**
     *  Handle the API Responses for json responses
     *   Additionally, create the RESPONSE entry an entry in system logs (system_logs)
     *
     * @return JsonResponse
     */
    public function toResponse(): JsonResponse
    {
        $this->createLogEntry($this->exception);

        if (
            $this->content instanceof AnonymousResourceCollection
            && $this->content->resource instanceof LengthAwarePaginator
        ) {
            return $this->content->toResponse($this->request);
        }

        return $this->jsonResponse();
    }

    protected function jsonResponse(): JsonResponse
    {
        return response()->json(
            $this->addDebugInformation(
                $this->formatResponse(),
                $this->exception
            ),
            $this->httpStatus,
            $this->headers,
            $this->options
        );
    }

    protected function formatResponse(): array
    {
        if ($this->exception) {
            if ($this->exception instanceof Error || $this->exception instanceof PDOException) {
                return [
                    'message' => $this->content,
                    'errors' => [],
                ];
            }

            return [
                'message' => $this->content,
                'errors' => $this->meta ?? [],
            ];
        }

        if ($this->meta) {
            return [
                'data' => $this->content,
                'meta' => $this->meta,
            ];
        }

        return [
            'data' => $this->content,
        ];
    }

    protected function addDebugInformation($content, Exception|Error|null $exception = null): array
    {
        if (!includeDebugInformation()) {
            return $content;
        }

        if (! is_array($content)) {
            $content = [
                'response_content' => $content,
            ];
        }

        $content['debug'] = $this->makeDebugInformation($exception);

        return $content;
    }

    protected function makeDebugInformation(Exception|Error|null $exception = null): array
    {
        $requestUri = request()->route() ? request()->route()->uri() : null;
        $sanitiseSensitiveDataInAdminResponses = isEnvironmentProduction()
            || config('engineering.sanitise_sensitive_data_in_admin_responses', true);

        $debugInformation = [
            'uri' => request()->path(),
            'request_uri' => $requestUri,
            'request_body' =>
                $sanitiseSensitiveDataInAdminResponses
                    ? sanitizeSensitiveData(
                        request()->all(),
                        config('engineering.sensitive_data.fields', []))
                    : request()->all(),
        ];

        if ($exception) {
            $message = $exception->getMessage();

            if ($exception instanceof SodiumException) {
                $message = sprintf(
                    '%s - Make sure to generate the .env parameters through the console command
                    `app:generate-app-keys` for APP_PRIVATE_KEY and APP_INDEX_KEY',
                    $exception->getMessage()
                );
            }

            $debugInformation = array_merge($debugInformation, [
                'message' => $message,
                'code' => $exception->getCode(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->map(function ($trace) {
                    return Arr::except($trace, ['args']);
                }),
            ]);
        }

        if (includeDebugQueriesInformation())
        {
            $debugInformation['queries'] = [];

            foreach (DatabaseConnection::values() as $connectionName) {
                foreach (DB::connection($connectionName)->getQueryLog() as $log) {
                    $debugInformation['queries'][$connectionName][] = [
                        'query' => $sanitiseSensitiveDataInAdminResponses
                            ? $log['query']
                            : constructQueryWithBindings($log['query'], $log['bindings']),
                        'time' => sprintf('%sms', $log['time']),
                    ];
                }
            }

            if ($exception instanceof QueryException) {
                $debugInformation['queries'][$exception->getConnectionName()][] = [
                    'query' => $sanitiseSensitiveDataInAdminResponses
                        ? $exception->getSql()
                        : constructQueryWithBindings($exception->getSql(), $exception->getBindings()),
                    'time' => '',
                ];
            }
        }

        return $debugInformation;
    }

    protected function createLogEntry(Exception|Error|null $exception = null): void
    {
        if ($exception instanceof Exception
            || $exception instanceof Error
            || ! config('engineering.log_all_api_responses')
            || ! isDebugModeEnabled()
        ) {
            return;
        }

        Log::info(
            sprintf('Successfully accessing endpoint [%s]', $this->request->getRequestUri()),
        );
    }
}
