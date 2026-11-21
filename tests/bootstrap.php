<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Magento generates *Factory classes at runtime (generated/code). Unit tests run without
// that step, so define a minimal stand-in with a create() method that tests can mock.
spl_autoload_register(static function (string $class): void {
    if (substr($class, -7) !== 'Factory' || !class_exists(substr($class, 0, -7))) {
        return;
    }
    $separator = strrpos($class, '\\');
    eval(sprintf(
        'namespace %s; class %s { public function create(array $data = []) { return null; } }',
        substr($class, 0, (int) $separator),
        substr($class, (int) $separator + 1)
    ));
});
