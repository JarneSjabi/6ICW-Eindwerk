<?php
// TabManager core class
namespace App\Core;

use App\Core\Config;

class TabManager
{
    protected array $tabs = [];
    protected string $activeTab;
    protected string $defaultTab;
    protected array $permissions = [];

    public function __construct(string $defaultTab = 'dashboard')
    {
        $this->defaultTab = $defaultTab;
        $this->activeTab = $_GET['tab'] ?? $defaultTab;
    }

    /**
     * Add a new tab
     */
    public function addTab(string $id, string $section, string $title, string $icon = null, string $permission = null): self
    {
        $this->tabs[$id] = [
            'id' => $id,
            'section' => $section,
            'title' => $title,
            'icon' => $icon,
            'permission' => $permission
        ];
        return $this;
    }

    /**
     * Get tab section friendly name
     */
    public function getTabSection(string $idSearch): string|null
    {
        $sections = Config::get('SECTIONS');
        foreach ($this->tabs as $id => $tab) {
            if (!empty($tab['section']) && $id == $idSearch) {
                return $sections[$tab['section']]["title"];
            }
        }

        return null;
    }

    /**
     * Set permissions for tabs
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Get active tab
     */
    public function getActiveTab(): string
    {
        return $this->activeTab;
    }

    /**
     * Check if tab exists
     */
    public function hasTab(string $id): bool
    {
        return isset($this->tabs[$id]);
    }

    /**
     * Check if user has permission for tab
     */
    protected function hasPermission(string $tabId): bool
    {
        $tab = $this->tabs[$tabId] ?? null;
        if (!$tab || !$tab['permission']) {
            return true;
        }

        return in_array($tab['permission'], $this->permissions);
    }

    /**
     * Get accessible tabs
     */
    public function getAccessibleTabs(): array
    {
        return array_filter($this->tabs, function ($tab) {
            return $this->hasPermission($tab['id']);
        });
    }

    /**
     * Get tab path
     */
    public function getTabPath(string $tabId): string
    {
        return __DIR__ . '/../tabs/' . $tabId . '.php';
    }

    /**
     * Render tab navigation
     */
    public function renderNavigation(): string
    {
        $html = '<ul class="nav-menu">';

        foreach ($this->getAccessibleTabs() as $tab) {
            $activeClass = $tab['id'] === $this->activeTab ? ' active' : '';
            $iconHtml = $tab['icon'] ? '<i class="fas ' . $tab['icon'] . '"></i>' : '';

            $html .= sprintf(
                '<li class="%s"><a href="?tab=%s">%s%s</a></li>',
                $activeClass,
                $tab['id'],
                $iconHtml,
                htmlspecialchars($tab['title'])
            );
        }

        $html .= '</ul>';
        return $html;
    }

    /**
     * Render active tab content
     */
    public function renderContent(): void
    {
        if (!$this->hasTab($this->activeTab) || !$this->hasPermission($this->activeTab)) {
            $this->activeTab = $this->defaultTab;
        }

        $tabPath = $this->getTabPath($this->activeTab);
        if (file_exists($tabPath)) {
            require $tabPath;
        } else {
            echo '<div class="alert alert-danger">Tab niet gevonden</div>';
        }
    }
}
