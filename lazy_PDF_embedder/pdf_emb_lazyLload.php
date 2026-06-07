/**
 * PRO версия 2.0: Lazy Load для PDF Embedder (Fix для динамической подгрузки)
 */
function lazy_load_pdf_embedder_final($atts, $content = null) {
    // 1. Формируем код плагина, но прячем его в скрытый блок
    // Используем div с display:none вместо template, чтобы скрипты точно отрендерились
    $real_embed_code = do_shortcode(shortcode_unautop('[pdf-embedder ' . build_query($atts) . ']'));
    
    // 2. Получаем ID и обложку
    $unique_id = 'pdf-lazy-' . uniqid();
    $cover_image_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
    
    // Стиль фона (обложка или градиент)
    $bg_style = $cover_image_url 
        ? "background-image: url('{$cover_image_url}');" 
        : "background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);";

    ob_start();
    ?>
    <style>
        .pdf-lazy-wrap { position: relative; width: 100%; min-height: 450px; background-size: cover; background-position: center; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .pdf-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 1; }
        .pdf-btn { position: relative; z-index: 2; background: #e74c3c; color: #fff; padding: 15px 30px; border: none; border-radius: 50px; font-size: 18px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: transform 0.2s; display: flex; align-items: center; gap: 10px; }
        .pdf-btn:hover { background: #c0392b; transform: scale(1.05); }
        .pdf-spinner { display: none; width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid #fff; border-radius: 50%; animation: spin 1s linear infinite; z-index: 3; position: relative; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* Состояние загрузки */
        .pdf-lazy-wrap.loading .pdf-btn { display: none; }
        .pdf-lazy-wrap.loading .pdf-spinner { display: block; }
        .pdf-lazy-wrap.loaded { background: none !important; min-height: auto; display: block; }
        .pdf-lazy-wrap.loaded .pdf-overlay, .pdf-lazy-wrap.loaded .pdf-spinner { display: none; }
    </style>

    <div id="<?php echo $unique_id; ?>" class="pdf-lazy-wrap" style="<?php echo $bg_style; ?>">
        <div class="pdf-overlay"></div>
        
        <button type="button" class="pdf-btn">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            Открыть документ
        </button>
        <div class="pdf-spinner"></div>

        <div class="pdf-container"></div>

        <script type="text/template" class="pdf-source">
            <?php echo $real_embed_code; ?>
        </script>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var wrapper = document.getElementById('<?php echo $unique_id; ?>');
        var btn = wrapper.querySelector('.pdf-btn');
        var container = wrapper.querySelector('.pdf-container');
        var sourceScript = wrapper.querySelector('.pdf-source');

        btn.addEventListener('click', function() {
            // 1. Включаем анимацию загрузки
            wrapper.classList.add('loading');

            setTimeout(function() {
                // 2. Берем HTML из скрытого скрипта-шаблона
                var htmlContent = sourceScript.innerHTML;
                container.innerHTML = htmlContent;

                // 3. МАГИЯ: Ищем скрипты внутри вставленного HTML и запускаем их вручную
                var scripts = container.getElementsByTagName("script");
                for (var i = 0; i < scripts.length; i++) {
                    // Если это инлайн скрипт (логика плагина)
                    if (!scripts[i].src && scripts[i].text) {
                        try {
                            // eval выполняет код в глобальной области видимости
                            window.eval(scripts[i].text);
                        } catch (e) {
                            console.error("PDF Lazy Load Error:", e);
                        }
                    }
                }

                // 4. Даем плагину время на инициализацию и убираем обложку
                if (window.jQuery) {
                    window.jQuery(window).trigger('resize'); // Обновляем размеры
                }
                
                wrapper.classList.add('loaded'); // Показываем PDF
                
            }, 300); // Небольшая задержка для плавности UI
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Перезаписываем шорткод плагина
if (!is_admin()) { // Не ломаем админку
    remove_shortcode('pdf-embedder');
    add_shortcode('pdf-embedder', 'lazy_load_pdf_embedder_final');
}