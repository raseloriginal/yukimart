<?php
$basePath = __DIR__;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), 'update') !== 0) {
        $content = file_get_contents($file->getPathname());
        $originalContent = $content;
        
        $relativePath = ($file->getPath() === $basePath) ? '' : '../';

        // 1. Replace the leaf icon in index.php
        $content = preg_replace(
            '/<div class="bg-brand-500 text-white w-10 h-10 flex items-center justify-center rounded">\s*<i class="fa-solid fa-leaf text-lg"><\/i>\s*<\/div>/',
            '<div class="flex items-center justify-center"><img src="' . $relativePath . 'assets/images/logo.png" alt="YukiMart" class="w-10 h-10 object-contain rounded"></div>',
            $content
        );

        // 2. Replace the leaf icon in admin sidebars and headers
        $content = preg_replace(
            '/<div class="bg-brand-500 text-white w-8 h-8 flex items-center justify-center rounded mr-3 shadow-sm">\s*<i class="fa-solid fa-leaf text-sm"><\/i>\s*<\/div>/',
            '<div class="flex items-center justify-center mr-3"><img src="' . $relativePath . 'assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded"></div>',
            $content
        );

        $content = preg_replace(
            '/<div class="bg-brand-500 text-white w-8 h-8 flex items-center justify-center rounded shadow-sm">\s*<i class="fa-solid fa-leaf text-sm"><\/i>\s*<\/div>/',
            '<div class="flex items-center justify-center"><img src="' . $relativePath . 'assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>',
            $content
        );

        // 3. Inject logo into login pages
        $content = preg_replace(
            '/<h1 class="text-2xl font-bold tracking-tight text-gray-900">YukiMart<span class="text-accent-500">BD<\/span><\/h1>/',
            '<img src="' . $relativePath . 'assets/images/logo.png" alt="Logo" class="w-12 h-12 object-contain mr-2">' . "\n            " . '<h1 class="text-2xl font-bold tracking-tight text-gray-900">YukiMart<span class="text-accent-500">BD</span></h1>',
            $content
        );

        $content = preg_replace(
            '/<h1 class="text-2xl font-bold tracking-tight text-gray-900">Yuki<span class="text-accent-500">Wholesale<\/span><\/h1>/',
            '<img src="' . $relativePath . 'assets/images/logo.png" alt="Logo" class="w-12 h-12 object-contain mr-2">' . "\n            " . '<h1 class="text-2xl font-bold tracking-tight text-gray-900">Yuki<span class="text-accent-500">Wholesale</span></h1>',
            $content
        );
        
        // Handle wholesaler sidebar and header if they use text logo instead of leaf
        // Actually earlier grep showed wholesaler/products.php has:
        // <div class="w-10 h-10 bg-brand-500 rounded flex items-center justify-center text-white font-bold text-2xl shadow-sm">W</div>
        // No wait, I already replaced that with h1 only. Let's make sure it doesn't duplicate.

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
        }
    }
}
echo "Logo updated.";
?>
