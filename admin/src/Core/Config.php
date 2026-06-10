<?php
// Configuration core class
namespace App\Core;

class Config
{
  private static array $config = array(
    /* Application Settings */
    'NAME' => 'Platform autonome mobiliteit',
    'TZ' => 'Europe/Brussels',
    'BASE_URL' => 'http://localhost',
    'MAINTENANCE_MODE' => false,
    'ERROR_REPORTING_OVERRIDE' => E_ERROR, // Set to null to use default error reporting based on DEBUG_MODE, or set to a specific value like E_ALL & ~E_NOTICE
    'DEBUG_MODE' => true, // Caution! Enabling Debug Mode disables some security checks like CORS validation
    /* Database Settings */
    'DB_HOST' => '127.0.0.1',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    'DB_DATABASE' => 'eindwerk',
    /* Logging Settings */
    'FULL_LOGGING_DISCORD_WEBHOOK_URL' => "",
    'HELPDESK_LOGGING_DISCORD_WEBHOOK_URL' => "",
    'DISCORD_LOGGING_ACTIONS_EXCLUDE' => [],
    /* Integrations */
    'BACKEND_SERVICE_URL' => 'http://localhost:5000/', // Backend service URL for route planning and vehicle management
    /* UI/UX Specific Settings */
    'QUOTES' => [
      "Een transformerende technologie.",
      "Autonome mobiliteit draait niet enkel om zelfrijdende auto’s, maar om een intelligent systeem waarin voertuigen, routes en passagiers continu worden geoptimaliseerd.",
      "Door de overstap van autobezit naar mobiliteit-als-dienst wordt inefficiëntie vervangen door slimme, vraaggestuurde inzet van voertuigen.",
      "De echte innovatie zit niet altijd in het voertuig zelf, maar in de software die alles met elkaar verbindt.",
      "Slimme ritplanning zal voor mobiliteit betekenen wat cloud computing deed voor IT: centralisatie van intelligentie en maximale efficiëntie.",
      "In een autonoom mobiliteitssysteem wachten voertuigen niet langer op passagiers—passagiers worden gekoppeld aan de meest optimale rit.",
      "Minder voertuigen, beter gebruik en gedeelde capaciteit vormen de sleutel tot duurzame mobiliteit.",
      "De toekomst van mobiliteit draait niet om rijden, maar om het slim organiseren van verplaatsingen.",
      "Verkeersproblemen worden oplosbaar wanneer mobiliteit wordt benaderd als een algoritmisch vraagstuk.",
      "Duurzame mobiliteit ontstaat wanneer voertuigen continu in gebruik zijn en ritten efficiënt worden gedeeld.",
      "De kracht van autonomie ligt niet in het wegnemen van de bestuurder, maar in het optimaliseren van het volledige mobiliteitssysteem."
    ],
    'SECTIONS' => [
      'overview' => ['title' => 'Overzicht', 'icon' => 'fa-home'],
      'vehicles' => ['title' => 'Voertuigen', 'icon' => 'fa-car'],
      'monitoring' => ['title' => 'Monitoring', 'icon' => 'fa-eye'],
      'reports' => ['title' => 'Rapporten', 'icon' => 'fa-chart-bar'],
      'configuration' => ['title' => 'Configuratie', 'icon' => 'fa-cog'],
    ],
    'TABS' => [
      // MAIN NAVIGATION
      'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'fa-home',
        'permission' => 'view_vehicles',
        'section' => 'overview'
      ],

      // VEHICLE MANAGEMENT
      'vehicles' => [
        'title' => 'Voertuigenlijst',
        'icon' => 'fa-car',
        'permission' => 'view_vehicles',
        'section' => 'vehicles'
      ],
      'vehicle_templates' => [
        'title' => 'Modellen',
        'icon' => 'fa-wrench',
        'permission' => 'view_vehicle_templates',
        'section' => 'vehicles'
      ],
      'rides' => [
        'title' => 'Ritten',
        'icon' => 'fa-list',
        'permission' => 'view_rides',
        'section' => 'vehicles'
      ],
      'routes' => [
        'title' => 'Routes',
        'icon' => 'fa-satellite',
        'permission' => 'view_routes',
        'section' => 'vehicles'
      ],

      // MONITORING
      'mapview' => [
        'title' => 'Kaartweergave',
        'icon' => 'fa-map',
        'permission' => 'view_vehicles',
        'section' => 'monitoring'
      ],
      'vehicle_location_reports' => [
        'title' => 'Locatie Rapporten',
        'icon' => 'fa-map-marker-alt',
        'permission' => 'view_vehicle_location_reports',
        'section' => 'monitoring'
      ],
      'ride_interrupts' => [
        'title' => 'Ritonderbrekingen',
        'icon' => 'fa-pause-circle',
        'permission' => 'view_ride_interrupts',
        'section' => 'monitoring'
      ],

      // CONFIGURATION
      'users' => [
        'title' => 'Gebruikers',
        'icon' => 'fa-user-friends',
        'permission' => 'view_users',
        'section' => 'configuration'
      ],
      'user_groups' => [
        'title' => 'Rollen',
        'icon' => 'fa-user-shield',
        'permission' => 'manage_users',
        'section' => 'configuration'
      ],
      'auditlogs' => [
        'title' => 'Logboeken',
        'icon' => 'fa-history',
        'permission' => 'manage_auditlog',
        'section' => 'configuration'
      ],
    
      // INTERNAL PAGES (not shown in main nav)
      'vehicle_detail' => [
        'title' => 'Voertuig Details',
        'icon' => 'fa-info-circle',
        'internal' => true,
        'permission' => 'view_vehicles'
      ],
      'user_detail' => [
        'title' => 'Gebruiker Details',
        'icon' => 'fa-user-circle',
        'internal' => true,
        'permission' => 'view_users'
      ],
      'route_detail' => [
        'title' => 'Route Details',
        'icon' => 'fa-route',
        'internal' => true,
        'permission' => 'view_routes'
      ]
    ]
  );

  /**
   * Get config value by key
   * @param string $key
   * @return mixed|null returns null if key does not exist or when the value itself is null
   */
  public static function get(string $key, mixed $default = null)
  {
    return self::$config[$key] ?? $default;
  }
}
