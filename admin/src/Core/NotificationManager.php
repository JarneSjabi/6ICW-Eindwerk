<?php

namespace App\Core;

class NotificationManager
{
    public static function add($title, $message = "", $type = 'info')
    {
        $alerts = Session::get('alerts', []);

        foreach ($alerts as $alert) {
            if ($alert['message'] === $message && $alert['type'] === $type) {
                return;
            }
        }

        $alerts[] = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'uniqid' => uniqid('alert_', true),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        Session::set('alerts', $alerts);
    }

    public static function getAll()
    {
        return Session::get('alerts', []);
    }

    public static function clear()
    {
        Session::remove('alerts');
    }

    public static function showAlerts()
    {
        $alerts = Session::get('alerts', []);
        if (empty($alerts)) return;
        echo '<script>';
        foreach ($alerts as $alert) {
            $icon = ($alert['type'] === 'danger' ? 'error' : ($alert['type'] === 'success' ? 'success' : ($alert['type'] === 'warning' ? 'warning' : 'info')));
            $title = addslashes($alert['title'] ?? ucfirst($alert['type']));
            $message = addslashes($alert['message'] ?? '');
            $footer = htmlspecialchars($alert['timestamp'] ?? '');
            echo "showAlert('$message', '$icon', '$title', '$footer');";
            
        }
        echo '</script>';

        
        echo "<script>
            document.querySelector('.swal2-confirm').addEventListener('click', function() {
                dismissAlert('" . $alert['uniqid'] . "');
            });
            </script>";
    }

    public static function dismissAlert($uniqid)
    {
        $alerts = Session::get('alerts', []);
        foreach ($alerts as $alert_id => $alert) {
            if ($alert['uniqid'] === $uniqid) {
                unset($_SESSION['alerts'][$alert_id]);
                return;
            }
        }
    }
}
