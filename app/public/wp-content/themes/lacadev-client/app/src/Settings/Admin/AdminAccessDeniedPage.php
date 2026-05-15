<?php

namespace App\Settings\Admin;

/**
 * Renders the branded admin access-denied screen.
 */
final class AdminAccessDeniedPage
{
    public static function render(string $logoUrl, string $adminUrl, string $website, string $authorName): string
    {
        $starsHtml = self::renderStars();
        $embersHtml = self::renderEmbers();

        return '
            <style>
' . self::css() . '
            </style>
            <div class="night-field" aria-hidden="true">
                <div class="alp-stars">' . $starsHtml . '</div>
                <div class="alp-moon"></div>
                <div class="alp-trees"><div class="alp-tree"></div><div class="alp-tree small"></div></div>
                <div class="alp-tent"></div>
                <div class="alp-fire-wrap">
                    <div class="alp-fire-glow"></div>
                    <div class="alp-embers">' . $embersHtml . '</div>
                    <div class="alp-flames"><div class="alp-flame"></div><div class="alp-flame"></div><div class="alp-flame"></div></div>
                    <div class="alp-firepit">
                        <div class="alp-logs">
                            <div class="alp-log" style="--r: 25deg"></div>
                            <div class="alp-log" style="--r: -25deg; margin-left: -15px"></div>
                        </div>
                        <div class="alp-rocks"><div class="alp-rock"></div><div class="alp-rock" style="margin-top:2px"></div><div class="alp-rock"></div></div>
                    </div>
                </div>
                <div class="alp-ground"></div>
            </div>
            <div class="denied-card">
                <div style="text-align:center">
                    <a target="_blank" href="' . esc_url($website) . '">
                        <img class="denied-logo" src="' . esc_url($logoUrl) . '" alt="' . esc_attr($authorName) . '">
                    </a>
                </div>
                <div class="denied-content">
                    <h2>Hết đường rồi, phượt thủ ơi!</h2>
                    <p>Đây là vùng cấm không dành cho bạn. <br>Hãy kiểm tra lại quyền hạn hoặc quay về trại chính nhé.</p>
                    <a class="back-link" href="' . esc_url($adminUrl) . '">Quay về Dashboard</a>
                </div>
            </div>
            <div class="footer-hint">// Peaceful Night </div>';
    }

    private static function renderStars(): string
    {
        $starsHtml = '';

        foreach (range(1, 100) as $i) {
            $size = rand(15, 35) / 10;
            $left = rand(0, 10000) / 100;
            $top = rand(0, 8500) / 100;
            $duration = rand(20, 50) / 10;
            $delay = rand(0, 50) / 10;

            $starsHtml .= '<div class="alp-star" style="left:' . $left
                . '%; top:' . $top
                . '%; width:' . $size
                . 'px; height:' . $size
                . 'px; --d:' . $duration
                . 's; animation-delay:' . $delay
                . 's; box-shadow: 0 0 ' . ($size + 1)
                . 'px #fff;"></div>';
        }

        return $starsHtml;
    }

    private static function renderEmbers(): string
    {
        $embersHtml = '';

        foreach (range(1, 8) as $i) {
            $x = rand(-15, 15);
            $tx = rand(-40, 40);
            $duration = rand(15, 40) / 10;
            $delay = rand(0, 30) / 10;
            $left = rand(42, 58);

            $embersHtml .= '<div class="alp-ember" style="--x:' . $x
                . 'px; --tx:' . $tx
                . 'px; --e-dur:' . $duration
                . 's; animation-delay:' . $delay
                . 's; left:' . $left
                . '%;"></div>';
        }

        return $embersHtml;
    }

    private static function css(): string
    {
        return <<<'CSS'
                html, body#error-page {
                    max-width: 100% !important;
                    width: 100vw !important;
                    height: 100vh !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: none !important;
                    box-shadow: none !important;
                    -webkit-box-shadow: none !important;
                    background: #05050a !important; /* Fallback */
                    background: radial-gradient(circle at 50% 40%, #1a2a4e 0%, #0d0d21 60%, #05050a 100%) !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    overflow: hidden !important;
                    font-family: "Quicksand", sans-serif !important;
                }
                #error-page h1 { display: none !important; }
                .night-field { position: fixed; inset: 0; z-index: 1; pointer-events: none; }
                .alp-star { position: absolute; border-radius: 50%; background: #ffffff; animation: alpTwinkle var(--d, 3s) ease-in-out infinite; opacity: 0.15; }
                @keyframes alpTwinkle { 0%, 100% { opacity: 0.2; transform: scale(0.8); } 50% { opacity: 1; transform: scale(1.2); } }
                .alp-moon { position: absolute; top: 10%; right: 15%; width: 45px; height: 45px; border-radius: 50%; box-shadow: 8px 8px 0 0 #fef9c3; filter: drop-shadow(0 0 15px rgba(254, 249, 195, 0.5)); transform: rotate(-10deg); z-index: 2; }
                .alp-ground { position: absolute; bottom: -20px; left: -10%; right: -10%; height: 160px; background: #020204; border-radius: 50% 50% 0 0; z-index: 4; }
                .alp-trees { position: absolute; bottom: 130px; right: 10%; display: flex; gap: 25px; z-index: 3; }
                .alp-tree { width: 0; height: 0; border-left: 28px solid transparent; border-right: 28px solid transparent; border-bottom: 90px solid #080f08; }
                .alp-tree.small { border-left-width: 20px; border-right-width: 20px; border-bottom-width: 60px; margin-top: 30px; }
                .alp-tent { position: absolute; bottom: 120px; left: 32%; width: 0; height: 0; border-left: 65px solid transparent; border-right: 65px solid transparent; border-bottom: 90px solid #1a3a5f; filter: drop-shadow(0 10px 25px rgba(0,0,0,0.6)); z-index: 5; }
                .alp-tent::after { content: ""; position: absolute; bottom: -90px; left: -22px; width: 0; height: 0; border-left: 22px solid transparent; border-right: 22px solid transparent; border-bottom: 45px solid #05080c; }
                .alp-fire-wrap { position: absolute; bottom: 125px; left: calc(32% + 140px); width: 50px; height: 50px; z-index: 5; }
                .alp-fire-glow { position: absolute; bottom: -20px; left: 50%; width: 250px; height: 100px; margin-left: -125px; background: radial-gradient(ellipse at center, rgba(255, 100, 0, 0.3) 0%, transparent 70%); animation: alpFirePulse 1.2s ease-in-out infinite alternate; }
                @keyframes alpFirePulse { from { opacity: 0.4; transform: scale(0.9); } to { opacity: 0.9; transform: scale(1.1); } }
                .alp-flame { position: absolute; bottom: 4px; left: 50%; width: 28px; height: 50px; background: #ff5e13; border-radius: 50% 50% 20% 20% / 80% 80% 20% 20%; filter: blur(1.5px); transform-origin: bottom center; animation: alpFlameMove 0.6s ease-in-out infinite alternate; margin-left: -14px; mix-blend-mode: screen; }
                .alp-flame:nth-child(2) { width: 22px; height: 40px; background: #ffcc33; animation-delay: 0.1s; filter: blur(1px); margin-left: -11px; }
                .alp-flame:nth-child(3) { width: 15px; height: 25px; background: #fff; animation-delay: 0.2s; filter: blur(0.5px); margin-left: -7.5px; }
                @keyframes alpFlameMove { 0% { transform: scale(1) rotate(-3deg); } 100% { transform: scale(1.1, 1.25) rotate(3deg); } }
                .alp-ember { position: absolute; bottom: 40px; width: 3px; height: 3px; background: #ffcc33; border-radius: 50%; filter: blur(0.5px); animation: alpEmberUp var(--e-dur, 2s) linear infinite; }
                @keyframes alpEmberUp { 0% { transform: translate(var(--x, 0), 0) scale(1); opacity: 1; } 100% { transform: translate(var(--tx, 0), -120px) scale(0); opacity: 0; } }
                .alp-firepit { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; }
                .alp-logs { display: flex; gap: 4px; margin-bottom: -2px; }
                .alp-log { width: 35px; height: 8px; background: #331a0a; border-radius: 4px; transform: rotate(var(--r, 20deg)); }
                .alp-rocks { display: flex; gap: 2px; }
                .alp-rock { width: 10px; height: 6px; background: #222; border-radius: 40%; }

                .denied-card {
                    width: 50rem;
                    max-width: 92vw;
                    background: rgba(255, 255, 255, 0.05) !important;
                    backdrop-filter: blur(40px) saturate(150%) !important;
                    -webkit-backdrop-filter: blur(40px) saturate(150%) !important;
                    border: 1px solid rgba(255, 255, 255, 0.1) !important;
                    border-radius: 4rem !important;
                    padding: 6rem 4rem !important;
                    box-shadow: 0 4rem 10rem rgba(0, 0, 0, 0.4) !important;
                    text-align: center !important;
                    position: relative !important;
                    z-index: 20 !important;
                    margin-top: -10vh;
                }
                .denied-logo { display: inline-block; width: 22rem; margin-bottom: 4rem; filter: brightness(0) invert(1); opacity: 0.8; }
                .denied-content h2 { font-size: 3.2rem; font-weight: 800; margin-bottom: 2rem; color: #fff; letter-spacing: -0.01em; }
                .denied-content p { font-size: 1.8rem; line-height: 1.7; margin-bottom: 4.5rem; color: rgba(255, 255, 255, 0.7); }
                .back-link { display: inline-flex; align-items: center; justify-content: center; padding: 0 5rem; height: 6.4rem; background: #fff; color: #05050a !important; text-decoration: none !important; border-radius: 1.6rem; font-weight: 800; font-size: 1.6rem; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                .back-link:hover { transform: scale(1.05) translateY(-5px); box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.3); }
                .footer-hint { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; color: rgba(255,255,255,0.2); font-family: monospace; font-size: 10px; letter-spacing: 4px; text-transform: uppercase; z-index: 5; }
CSS;
    }
}
