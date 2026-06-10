<?php


namespace App\Core;

class Utils
{
    private static $cachedBody = null;

    public static function getRequestData()
    {
        if (self::$cachedBody !== null) {
            return self::$cachedBody;
        }

        $json = file_get_contents('php://input');
        self::$cachedBody = json_decode($json, true) ?? [];
        return self::$cachedBody;
    }

    public static function setRequestData(array $data)
    {
        self::$cachedBody = $data;
    }

    
    public static function isAllowedOrigin(array $allowedOrigins): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        
        if ($origin) {
            foreach ($allowedOrigins as $allowed) {
                if (stripos($origin, $allowed) === 0) return true;
            }
            return false;
        }

        
        if ($referer) {
            $host = parse_url($referer, PHP_URL_HOST);
            foreach ($allowedOrigins as $allowed) {
                $allowedHost = parse_url($allowed, PHP_URL_HOST);
                if ($host && $allowedHost && strcasecmp($host, $allowedHost) === 0) return true;
            }
            return false;
        }

        
        return false;
    }

    public static function truncateString(string $text, int $maxLength = 50): string
    {
        $cleanText = htmlspecialchars($text);
        if (strlen($cleanText) <= $maxLength) {
            return $cleanText;
        }
        return substr($cleanText, 0, $maxLength) . '...';
    }

    public static function renderDiffVisual($oldJson, $newJson)
    {
        $old = $oldJson ? json_decode($oldJson, true) : [];
        $new = $newJson ? json_decode($newJson, true) : [];

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        $html = "<div class='diff-list'>";
        foreach ($keys as $k) {
            $oldVal = array_key_exists($k, $old) ? $old[$k] : null;
            $newVal = array_key_exists($k, $new) ? $new[$k] : null;

            if ($oldVal === $newVal) {
                $html .= "<div class='diff-row'><span class='diff-key'>{$k}</span>: <span class='diff-same'>" . htmlspecialchars((string)$newVal) . "</span></div>";
            } else {
                if ($oldVal === null) {
                    $html .= "<div class='diff-row'><span class='diff-key'>{$k}</span>: <span class='diff-added'>+ " . htmlspecialchars((string)$newVal) . "</span></div>";
                } elseif ($newVal === null) {
                    $html .= "<div class='diff-row'><span class='diff-key'>{$k}</span>: <span class='diff-removed'>- " . htmlspecialchars((string)$oldVal) . "</span></div>";
                } else {
                    $html .= "<div class='diff-row'><span class='diff-key'>{$k}</span>: <span class='diff-removed'>" . htmlspecialchars((string)$oldVal) . "</span> <span class='diff-arrow'>&rarr;</span> <span class='diff-added'>" . htmlspecialchars((string)$newVal) . "</span></div>";
                }
            }
        }
        $html .= "</div>";

        return $html;
    }

    public static function renderChangelog(string $entityType, int $entityId)
    {
        $Authentication = new Authentication();
        if (!$Authentication->hasPermission('manage_auditlog')) {
            return;
        }
        $auditLogs = Audit::getLogsForEntity($entityType, $entityId);

        echo "<style>
    /* Collapsible audit diff */
    .audit-toggle {
        display: block;
        width: 100%;
        text-align: left;
        background: #f8f9fb;
        border: 1px solid #e7e9ee;
        padding: 6px 8px;
        margin: 6px 0;
        border-radius: 4px;
        cursor: pointer
    }

    .audit-diff {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.2s ease;
        padding: 0 6px
    }

    .audit-diff.open {
        max-height: 400px;
        padding: 8px 6px
    }

    .meta-extra {
        margin-top: 8px;
        font-size: 12px;
        color: #666
    }
</style>";

        ob_start();
?>
        <aside class="detail-sidebar">
            <?php if (empty($auditLogs)): ?>
                <div class="empty-state">
                    <p>Geen wijzigingen gevonden voor deze <?= $entityType ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($auditLogs as $log): ?>
                    <div class="audit-entry">
                        <div class="audit-meta">
                            <div class="audit-meta-row">
                                <div>
                                    <strong><i class="<?= htmlspecialchars($log['action_icon']) ?>"></i> <?= htmlspecialchars($log['action_label'] ?? $log['action']) ?></strong>
                                    <div class="meta-small">Op <?= htmlspecialchars($log['created_at']) ?></div>
                                </div>
                                <div class="audit-meta-user">
                                    <div><?= htmlspecialchars($log['display_name'] ?? 'System') ?></div>
                                    <div title="IP / agent">IP: <?= htmlspecialchars($log['ip_address'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="audit-actions">
                            <button class="audit-toggle" onclick="this.closest('.audit-entry').querySelector('.audit-diff').classList.toggle('open')">
                                <i class="fa fa-eye"></i> Bekijk wijzigingen
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="revertAudit(<?= (int)$log['id'] ?>)"><i class="fa fa-undo"></i> Ongedaan maken</button>
                        </div>
                        <div class="audit-diff">
                            <?= Utils::renderDiffVisual($log['old_value'], $log['new_value']) ?>
                            <div class="meta-extra">User agent: <?= htmlspecialchars($log['user_agent'] ?? '-') ?></div>
                        </div>
                    </div>
                    <hr>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>
<?php
        return ob_get_clean();
    }
}
