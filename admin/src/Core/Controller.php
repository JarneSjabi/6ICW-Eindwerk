<?php
// Abstract class for Controllers
namespace App\Core;

use App\Core\Request;
use App\Core\Hookable;

abstract class Controller
{
    use Hookable;

    protected $modelClass;

    /* Primary or secondary mode
     * Primary mode will use Request parameters for index method (search, page, limit)
     * Secondary mode will require these to be passed explicitly
     * */
    public const PRIMARY_MODE = 'primary';
    public const SECONDARY_MODE = 'secondary';

    protected string $mode = self::PRIMARY_MODE;

    public function __construct($mode = self::PRIMARY_MODE)
    {
        $this->mode = strtolower($mode);
        // Auto-detect model class based on controller name
        if (!$this->modelClass) {
            $controllerName = (new \ReflectionClass($this))->getShortName();
            $modelName = str_replace('Controller', '', $controllerName);
            $this->modelClass = "App\\Models\\{$modelName}";
        }

        // Register hooks
        $this->registerHooks();
    }

    protected function registerHooks()
    {
        // Default implementation - override in child controllers
        /* Example:
            // Hook that runs before updating
            $this->registerHook('update', function () {
                // do something
                return true;
            }, "before");
        */
    }

    /* Optional parameters filters, search, page and limit will only be accepted in secondary mode */
    protected function index($filters = [], $search = '', $page = 1, $limit = 50000)
    {
        try {
            if ($this->mode === self::PRIMARY_MODE) {
                $filters = json_decode(Request::get('filters', "{}"));
                $search = Request::get('search', '');
                $page = (int)Request::get('page', 1);
                $limit = (int)Request::get('limit', 20);
            }

            $model = $this->getModel();
            $result = $model::getPaginated($search, $page, $limit, $filters);

            return $this->success('Data opgehaald', $result);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen data: ' . $e->getMessage());
        }
    }

    protected function show($id)
    {
        try {
            $model = $this->getModel();
            $record = $model::find($id);

            if (!$record) {
                return $this->error('Record niet gevonden', [], 404);
            }

            return $this->success('Record gevonden', $record->toArray());
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen record: ' . $e->getMessage());
        }
    }

    protected function store()
    {
        try {
            $data = Utils::getRequestData();
            $model = $this->getModel();

            // Validation
            $validation = $this->validateData($data);
            if (!$validation['success']) {
                return $this->error('Validatie mislukt', $validation['errors'], 422);
            }

            // Create new instance
            $record = new $model();
            $record_before = clone $record;
            $record->fill($data);

            if ($record->save()) {
                // Create audit log
                $old_value = $record_before->toArray();
                $new_value = $record->toArray();
                Audit::log(get_class($record) . '__store', get_class($record), $record->id, $old_value, $new_value);

                return $this->success('Record succesvol aangemaakt', [
                    'id' => $record->id
                ], 201);
            }

            return $this->error('Fout bij aanmaken record');
        } catch (\Exception $e) {
            return $this->error('Fout bij aanmaken record: ' . $e->getMessage());
        }
    }

    protected function update($id)
    {
        try {
            $model = $this->getModel();
            $record = $model::find($id);

            if (!$record) {
                return $this->error('Record niet gevonden', [], 404);
            }

            $data = Utils::getRequestData();

            // Validation
            $validation = $this->validateData($data, $id);
            if (!$validation['success']) {
                return $this->error('Validatie mislukt', $validation['errors'], 422);
            }

            $record_before = clone $record;
            $record->fill($data);

            if ($record->save()) {
                // Create audit log
                $old_value = $record_before->toArray();
                $new_value = $record->toArray();
                Audit::log(get_class($record) . '__update', get_class($record), $record->id, $old_value, $new_value);

                return $this->success('Record succesvol bijgewerkt', [
                    'id' => $record->id
                ], 201);
            }

            return $this->error('Fout bij bijwerken record');
        } catch (\Exception $e) {
            return $this->error('Fout bij bijwerken record: ' . $e->getMessage());
        }
    }

    protected function destroy($id)
    {
        try {
            $model = $this->getModel();
            $record = $model::find($id);

            if (!$record) {
                return $this->error('Record niet gevonden', [], 404);
            }

            // Check dependencies
            if (!$record->canDelete()) {
                return $this->error($record->getDeleteErrorMessage());
            }

            if ($record->delete()) {
                // Create audit log
                $old_value = $record->toArray();
                $new_value = null;
                Audit::log(get_class($record) . '__destroy', get_class($record), $record->id, $old_value, $new_value);

                return $this->success('Record succesvol verwijderd');
            }

            return $this->error('Fout bij verwijderen record');
        } catch (\Exception $e) {
            return $this->error('Fout bij verwijderen record: ' . $e->getMessage());
        }
    }

    // Helper methods
    protected function getModel()
    {
        return $this->modelClass;
    }

    protected function validateData(array $data, $id = null)
    {
        // Basic validation - override in child controllers
        return ['success' => true, 'errors' => []];
    }

    // Response methods
    protected function success($message = 'Success', $data = null, $status = 200)
    {
        return $this->formatResponse([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $status);
    }

    protected function error($message = 'Error', $data = null, $status = 400)
    {
        return $this->formatResponse([
            'success' => false,
            'message' => $message,
            'data'    => $data
        ], $status);
    }

    /**
     * Smart response handler: 
     * - On AJAX → JSON output + exit
     * - On a normal call → return raw array
     */
    protected function formatResponse(array $payload, int $status = 200)
    {
        if (Request::isAjaxRequest()) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode($payload);
            exit;
        }

        // To use in a PHP view context
        return $payload;
    }

    protected function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
