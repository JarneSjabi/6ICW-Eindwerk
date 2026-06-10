<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Utils;
use App\Core\Request;
use App\Models\User;

class UserController extends Controller
{
    protected $table = 'users';

    
    protected function statistics()
    {
        try {
            $stats = User::getDashboardStats();
            return $this->success('Statistieken opgehaald', $stats);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen statistieken: ' . $e->getMessage());
        }
    }

    
    protected function update($id = null)
    {
        try {
            if (!$id) {
                $id = Request::get('id', null);
            }

            if (!$id) {
                return $this->error('Geen ID opgegeven', null, 400);
            }

            $data = Utils::getRequestData();
            
            
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']); 
            } else {
                
                unset($data['password_hash']);
            }

            $model = $this->getModel();
            $record = $model::find($id);

            if (!$record) {
                return $this->error('Record niet gevonden', [], 404);
            }

            
            $fillable = $record->fillable;
            foreach ($data as $key => $value) {
                if (in_array($key, $fillable)) {
                    $record->$key = $value;
                }
            }

            $record->save();

            return $this->success('Gebruiker bijgewerkt', $record->toArray());
        } catch (\Exception $e) {
            return $this->error('Fout bij bijwerken gebruiker: ' . $e->getMessage());
        }
    }
}
