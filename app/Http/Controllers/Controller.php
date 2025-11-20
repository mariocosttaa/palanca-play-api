<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Track if we started a transaction in this controller method
     * This helps handle SQLite nested transaction issues in tests
     */
    private ?bool $transactionStarted = null;

    /**
     * Safely begin a database transaction.
     * Only starts a new transaction if not already in one (e.g., SQLite in tests)
     */
    protected function beginTransactionSafe(): void
    {
        if (DB::transactionLevel() === 0) {
            DB::beginTransaction();
            $this->transactionStarted = true;
        } else {
            $this->transactionStarted = false;
        }
    }

    /**
     * Safely commit a database transaction.
     * Only commits if we started the transaction in this method
     */
    protected function commitSafe(): void
    {
        if ($this->transactionStarted === true) {
            DB::commit();
        }
    }

    /**
     * Safely rollback a database transaction.
     * Only rolls back if we started the transaction in this method
     */
    protected function rollBackSafe(): void
    {
        if ($this->transactionStarted === true) {
            DB::rollBack();
        }
    }

    public function errorResponse(string $message, ?string $errorlog = null, int $status = 400)
    {
        if ($errorlog) {
            logger()->error('error response in controller', [
                'message' => $message,
                'error' => $errorlog,
                'status' => $status,
            ]);
        }

        return response()->json([
            'message' => $message,
        ], $status);
    }

    public function successResponse(string $message, int $status = 200)
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    public function dataResponse(mixed $data, int $status = 200)
    {
        return response()->json([
            'data' => $data,
        ], $status);
    }
}
