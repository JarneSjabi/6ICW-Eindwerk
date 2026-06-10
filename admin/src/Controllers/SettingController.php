<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Utils;
use App\Models\Setting;

class SettingController extends Controller
{
    protected $table = 'settings';

    
    public function index($filters = [], $search = '', $page = 1, $limit = 50000)
    {
        try {
            
            if ($this->mode === self::PRIMARY_MODE) {
                $category = Request::get('category', null);
            } else {
                
                $category = $filters['category'] ?? null;
            }
            
            $settings = Setting::getByCategory($category);
            $categories = Setting::getCategories();
            
            
            $grouped = [];
            foreach ($settings as $setting) {
                $cat = $setting->category ?? 'general';
                if (!isset($grouped[$cat])) {
                    $grouped[$cat] = [];
                }
                $grouped[$cat][] = $setting->toArray();
            }
            
            return $this->success('Settings opgehaald', [
                'settings' => $grouped,
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            return $this->error('Fout bij ophalen settings: ' . $e->getMessage());
        }
    }

    
    public function update($key = null)
    {
        try {
            if (!$key) {
                $key = Request::get('key', null);
            }
            
            if (!$key) {
                return $this->error('Geen key opgegeven', null, 400);
            }
            
            $data = Utils::getRequestData();
            $value = $data['value'] ?? null;
            $type = $data['type'] ?? 'string';
            
            Setting::set($key, $value, $type);
            
            return $this->success('Setting bijgewerkt');
        } catch (\Exception $e) {
            return $this->error('Fout bij bijwerken setting: ' . $e->getMessage());
        }
    }

    
    public function bulkUpdate()
    {
        try {
            $data = Utils::getRequestData();
            $settings = $data['settings'] ?? [];
            
            $updated = 0;
            foreach ($settings as $key => $settingData) {
                $value = $settingData['value'] ?? null;
                $type = $settingData['type'] ?? 'string';
                
                Setting::set($key, $value, $type);
                $updated++;
            }
            
            return $this->success("{$updated} settings bijgewerkt");
        } catch (\Exception $e) {
            return $this->error('Fout bij bulk update: ' . $e->getMessage());
        }
    }
}

