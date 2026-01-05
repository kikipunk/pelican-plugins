<?php

namespace Kikipunk\PelicanWaters;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\File;

class PelicanWatersPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pelican-waters';
    }

    public function register(Panel $panel): void
    {
        // Copy CSS files to public directory
        $pairs = [
            [__DIR__ . '/../css/pelican-waters-light.css', public_path('plugins/pelican-waters/css/pelican-waters-light.css')],
            [__DIR__ . '/../css/pelican-waters-dark.css', public_path('plugins/pelican-waters/css/pelican-waters-dark.css')],
        ];

        foreach ($pairs as [$source, $destination]) {
            try {
                if (!File::exists($destination) && File::exists($source)) {
                    $dir = dirname($destination);
                    if (!File::isDirectory($dir)) {
                        File::makeDirectory($dir, 0755, true);
                    }
                    File::copy($source, $destination);
                }
            } catch (\Throwable $e) {
                // Silently fail if we can't copy the file
            }
        }

        // Set font and colors
        $panel->font('Gloria Hallelujah');

        $panel->colors([
            'danger' => Color::Rose,
            'gray' => Color::Slate,
            'info' => Color::Sky,
            'primary' => Color::hex('#1F6FA3'), // Bleu lac
            'success' => Color::hex('#5FB3B3'), // Turquoise doux
            'warning' => Color::Amber,
        ]);

        // Inject CSS based on theme
        $panel->renderHook('panels::head.end', function () {
            $light = asset('plugins/pelican-waters/css/pelican-waters-light.css');
            $dark = asset('plugins/pelican-waters/css/pelican-waters-dark.css');

            return <<<HTML
<link id="pelican-waters-css" rel="stylesheet">
<script>
(function () {
    var light = "{$light}";
    var dark = "{$dark}";

    function detectTheme() {
        if (document.documentElement && document.documentElement.classList.contains('dark')) {
            return 'dark';
        }
        var htmlTheme = document.documentElement && document.documentElement.getAttribute('data-theme');
        if (htmlTheme) {
            return htmlTheme.toLowerCase() === 'dark' ? 'dark' : 'light';
        }
        try {
            var keys = ['filament_theme', 'theme', 'filamentTheme'];
            for (var i = 0; i < keys.length; i++) {
                var val = localStorage.getItem(keys[i]);
                if (val) {
                    var lv = val.toLowerCase();
                    if (lv === 'dark' || lv === 'light') return lv;
                }
            }
        } catch (e) {}
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    function applyTheme() {
        var theme = detectTheme();
        var el = document.getElementById('pelican-waters-css');
        if (!el) {
            el = document.createElement('link');
            el.rel = 'stylesheet';
            el.id = 'pelican-waters-css';
            document.head.appendChild(el);
        }
        var target = theme === 'dark' ? dark : light;
        if (el.getAttribute('href') !== target) {
            el.setAttribute('href', target);
        }
        // Update pelican and ripples colors
        document.documentElement.style.setProperty('--pw-theme', theme);
    }

    applyTheme();

    try {
        var obs = new MutationObserver(applyTheme);
        if (document.documentElement) {
            obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
        }
    } catch (e) {}

    window.addEventListener('storage', function (e) {
        if (e && e.key === 'filament_theme') applyTheme();
    });
    window.addEventListener('theme-changed', applyTheme);

    if (window.matchMedia) {
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);
        } catch (e) {
            try { window.matchMedia('(prefers-color-scheme: dark)').addListener(applyTheme); } catch (e) {}
        }
    }
})();
</script>
HTML;
        });

        // Inject decorative elements (pelican, waves, ripples)
        $panel->renderHook('panels::body.start', function () {
            return <<<'HTML'
<!-- Pelican Waters Decorations -->
<div id="pw-decorations" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; overflow: hidden;">

    <!-- Animated Waves (continuous scroll) -->
    <div id="pw-waves" style="position: absolute; bottom: 0; left: 0; width: 100%; height: 150px; overflow: hidden;">
        <svg class="pw-waves-svg" viewBox="0 24 150 28" preserveAspectRatio="none" style="position: absolute; bottom: 0; width: 200%; height: 100%;">
            <defs>
                <path id="wave-path" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z"/>
            </defs>
            <g class="pw-wave-parallax">
                <use xlink:href="#wave-path" class="pw-wave pw-wave-1" x="48" y="0"/>
                <use xlink:href="#wave-path" class="pw-wave pw-wave-2" x="48" y="3"/>
                <use xlink:href="#wave-path" class="pw-wave pw-wave-3" x="48" y="5"/>
                <use xlink:href="#wave-path" class="pw-wave pw-wave-4" x="48" y="7"/>
            </g>
        </svg>
    </div>

    <!-- Water Ripples Container -->
    <div id="pw-ripples" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></div>
</div>

<style>
/* Wave animations - continuous horizontal scroll */
.pw-waves-svg {
    animation: pw-wave-scroll 25s linear infinite;
    transform: translate3d(0, 0, 0);
}

.pw-wave {
    fill: rgba(95, 179, 179, 0.7);
}
.pw-wave-1 {
    fill: rgba(95, 179, 179, 0.7);
    animation: pw-wave-move 7s ease-in-out infinite alternate;
}
.pw-wave-2 {
    fill: rgba(95, 179, 179, 0.5);
    animation: pw-wave-move 10s ease-in-out -2s infinite alternate-reverse;
}
.pw-wave-3 {
    fill: rgba(95, 179, 179, 0.3);
    animation: pw-wave-move 13s ease-in-out -4s infinite alternate;
}
.pw-wave-4 {
    fill: rgba(95, 179, 179, 0.2);
    animation: pw-wave-move 16s ease-in-out -1s infinite alternate-reverse;
}

/* Dark mode wave colors */
:root[style*="--pw-theme: dark"] .pw-wave-1,
.dark .pw-wave-1 {
    fill: rgba(79, 209, 197, 0.7);
}
:root[style*="--pw-theme: dark"] .pw-wave-2,
.dark .pw-wave-2 {
    fill: rgba(79, 209, 197, 0.5);
}
:root[style*="--pw-theme: dark"] .pw-wave-3,
.dark .pw-wave-3 {
    fill: rgba(79, 209, 197, 0.3);
}
:root[style*="--pw-theme: dark"] .pw-wave-4,
.dark .pw-wave-4 {
    fill: rgba(79, 209, 197, 0.2);
}

@keyframes pw-wave-scroll {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(-50%, 0, 0); }
}

@keyframes pw-wave-move {
    0% { transform: translate3d(-30px, 0, 0); }
    100% { transform: translate3d(30px, 0, 0); }
}

/* Ripple styling */
.pw-ripple {
    position: absolute;
    border-radius: 50%;
    border: 2px solid var(--pw-ripple-color, rgba(95, 179, 179, 0.4));
    animation: pw-ripple-expand 3s ease-out forwards;
    pointer-events: none;
}
:root[style*="--pw-theme: dark"] .pw-ripple,
.dark .pw-ripple {
    border-color: rgba(79, 209, 197, 0.3);
}

@keyframes pw-ripple-expand {
    0% {
        width: 0;
        height: 0;
        opacity: 0.6;
    }
    100% {
        width: 150px;
        height: 150px;
        opacity: 0;
        margin-left: -75px;
        margin-top: -75px;
    }
}
</style>

<script>
(function() {
    // Ripple generator
    function createRipple() {
        var container = document.getElementById('pw-ripples');
        if (!container) return;

        var ripple = document.createElement('div');
        ripple.className = 'pw-ripple';
        ripple.style.left = (10 + Math.random() * 80) + '%';
        ripple.style.top = (10 + Math.random() * 80) + '%';

        container.appendChild(ripple);

        setTimeout(function() {
            ripple.remove();
        }, 3000);
    }

    // Create ripples periodically
    function startRipples() {
        createRipple();
        setInterval(function() {
            if (Math.random() > 0.3) createRipple();
        }, 2000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startRipples);
    } else {
        startRipples();
    }
})();
</script>
HTML;
        });
    }

    public function boot(Panel $panel): void {}
}
