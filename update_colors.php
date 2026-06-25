<?php
$basePath = __DIR__;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'update_colors.php') {
        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        // Remove inline tailwind config
        $content = preg_replace('/<script>\s*tailwind\.config\s*=\s*\{.*?<\/script>/s', '', $content);

        // Add tailwind config script
        $relativePath = ($file->getPath() === $basePath) ? '' : '../';
        $cdnRegex = '/<script src="https:\/\/cdn\.tailwindcss\.com"><\/script>/';
        
        if (strpos($content, 'tailwind.config.js') === false) {
            $replacement = "<script src=\"https://cdn.tailwindcss.com\"></script>\n    <script src=\"{$relativePath}assets/js/tailwind.config.js\"></script>";
            $content = preg_replace($cdnRegex, $replacement, $content);
        }

        // Replace old colors with brand
        $content = str_replace(
            ['emerald-500', 'emerald-600', 'emerald-700', 'emerald-50', 'emerald-100', 'purple-500', 'purple-600', 'purple-700', 'purple-50', 'purple-100'],
            ['brand-500', 'brand-600', 'brand-700', 'brand-50', 'brand-100', 'brand-500', 'brand-600', 'brand-700', 'brand-50', 'brand-100'],
            $content
        );

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
        }
    }
}
echo "Done.";
?>
