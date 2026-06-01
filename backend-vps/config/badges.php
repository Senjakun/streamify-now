<?php
// Badge system untuk playall.me
define('BADGES', [
    // ── Fantasy Race ──────────────────────────────────────────
    'Demon'     => ['icon'=>'👿','color'=>'red','category'=>'fantasy'],
    'Angel'     => ['icon'=>'👼','color'=>'sky','category'=>'fantasy'],
    'Hujan'     => ['icon'=>'🌧️','color'=>'blue','category'=>'fantasy'],
    'Dwarf'     => ['icon'=>'⚒️','color'=>'orange','category'=>'fantasy'],
    'Elf'       => ['icon'=>'🧝','color'=>'green','category'=>'fantasy'],
    'High Elf'  => ['icon'=>'✨','color'=>'emerald','category'=>'fantasy'],

    // ── Bangsawan ─────────────────────────────────────────────
    'Baron'     => ['icon'=>'🎖️','color'=>'zinc','category'=>'noble'],
    'Count'     => ['icon'=>'📜','color'=>'slate','category'=>'noble'],
    'Marquis'   => ['icon'=>'🏅','color'=>'purple','category'=>'noble'],
    'Duke'      => ['icon'=>'🏰','color'=>'violet','category'=>'noble'],
    'Duchess'   => ['icon'=>'🦁','color'=>'fuchsia','category'=>'noble'],
    'King'      => ['icon'=>'🤴','color'=>'amber','category'=>'noble'],
    'Emperor'   => ['icon'=>'👑','color'=>'yellow','category'=>'noble'],

    // ── Modern ────────────────────────────────────────────────
    'CEO'       => ['icon'=>'💼','color'=>'indigo','category'=>'modern'],
    'Orang Kaya'=> ['icon'=>'💰','color'=>'lime','category'=>'modern'],

    // ── Special ───────────────────────────────────────────────
    'Penduduk Desa' => ['icon'=>'👤','color'=>'gray','category'=>'default'],
    'Tuhan'     => ['icon'=>'🌟','color'=>'gold','category'=>'admin'],
]);

function getBadgeStyle($badge) {
    $badges = BADGES;
    $b = $badges[$badge] ?? $badges['Penduduk Desa'];
    $colorMap = [
        'red'     => ['bg'=>'#7f1d1d','text'=>'#fca5a5','border'=>'#ef4444'],
        'sky'     => ['bg'=>'#0c4a6e','text'=>'#7dd3fc','border'=>'#0ea5e9'],
        'blue'    => ['bg'=>'#1e3a5f','text'=>'#93c5fd','border'=>'#3b82f6'],
        'orange'  => ['bg'=>'#7c2d12','text'=>'#fdba74','border'=>'#f97316'],
        'green'   => ['bg'=>'#14532d','text'=>'#86efac','border'=>'#22c55e'],
        'emerald' => ['bg'=>'#064e3b','text'=>'#6ee7b7','border'=>'#10b981'],
        'zinc'    => ['bg'=>'#27272a','text'=>'#d4d4d8','border'=>'#71717a'],
        'slate'   => ['bg'=>'#1e293b','text'=>'#cbd5e1','border'=>'#64748b'],
        'purple'  => ['bg'=>'#3b0764','text'=>'#d8b4fe','border'=>'#a855f7'],
        'violet'  => ['bg'=>'#2e1065','text'=>'#c4b5fd','border'=>'#8b5cf6'],
        'fuchsia' => ['bg'=>'#4a044e','text'=>'#f0abfc','border'=>'#d946ef'],
        'amber'   => ['bg'=>'#78350f','text'=>'#fcd34d','border'=>'#f59e0b'],
        'yellow'  => ['bg'=>'#713f12','text'=>'#fde68a','border'=>'#eab308'],
        'indigo'  => ['bg'=>'#1e1b4b','text'=>'#a5b4fc','border'=>'#6366f1'],
        'lime'    => ['bg'=>'#1a2e05','text'=>'#bef264','border'=>'#84cc16'],
        'gray'    => ['bg'=>'#1f2937','text'=>'#9ca3af','border'=>'#4b5563'],
        'gold'    => ['bg'=>'linear-gradient(135deg,#78350f,#92400e)','text'=>'#fde68a','border'=>'#f59e0b','glow'=>'0 0 10px #f59e0b80'],
    ];
    return array_merge($b, $colorMap[$b['color']] ?? $colorMap['gray']);
}
