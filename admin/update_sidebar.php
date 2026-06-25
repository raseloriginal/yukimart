<?php
$files = ['dashboard.php', 'orders.php', 'products.php', 'users.php', 'areas.php', 'settings.php'];

foreach ($files as $file) {
    $path = 'c:/xampp/htdocs/yukimart/admin/' . $file;
    if (!file_exists($path)) continue;

    $content = file_get_contents($path);
    
    // Check if map.php is already there
    if (strpos($content, 'href="map.php"') !== false) {
        continue;
    }

    $areaLink = '<a href="areas.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-map-location-dot w-5 text-center"></i> Delivery Areas
            </a>';
            
    $areaLinkActive = '<a href="areas.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
                <i class="fa-solid fa-map-location-dot w-5 text-center"></i> Delivery Areas
            </a>';

    $mapLink = '
            <a href="map.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-map w-5 text-center"></i> Delivery Map
            </a>';

    if (strpos($content, $areaLink) !== false) {
        $content = str_replace($areaLink, $areaLink . $mapLink, $content);
    } elseif (strpos($content, $areaLinkActive) !== false) {
        $content = str_replace($areaLinkActive, $areaLinkActive . $mapLink, $content);
    }

    file_put_contents($path, $content);
    echo "Updated $file\n";
}
?>
