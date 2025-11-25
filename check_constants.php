<?php
$constants = get_defined_constants(true);
if (isset($constants['firebird'])) {
    print_r($constants['firebird']);
} else {
    echo "Firebird extension not loaded or constants not found under 'firebird' category.\n";
    // Setup fallback to check all constants starting with IBASE_ or FBIRD_
    $all = get_defined_constants();
    foreach ($all as $key => $value) {
        if (str_starts_with($key, 'IBASE_') || str_starts_with($key, 'FBIRD_')) {
            echo "$key => $value\n";
        }
    }
}
