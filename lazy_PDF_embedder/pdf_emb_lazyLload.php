/**
 * PRO версия: Lazy Load для PDF Embedder со стилями и спиннером
 */
function lazy_load_pdf_embedder_pro($atts, $content = null) {
    // 1. Получаем код плагина, но не выводим его
    $real_embed_code = do_shortcode(shortcode_unautop('[pdf-embedder ' . build_query($atts) . ']'));
    
    // 2. ID и Обложка
    $unique_id = 'pdf-wrap-' . uniqid();
    $cover_image_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
    
    // Если обложки нет, используем нейтральный паттерн или серый фон
    $bg_style = $cover_image_url 
        ? "background-image: url('{$cover_image_url}');" 
        : "background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);";

    ob_start();
    ?>
    
    <style>
        .pdf-lazy-wrapper {
            position: relative;
            width: 100%;
            min-height: 500px; /* Высота превью по умолчанию */
            background-color: #eee;
            background-size: cover;
            background-position: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pdf-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5); /* Затемнение фона */
            transition: opacity 0.3s;
            z-index: 1;
            backdrop-filter: blur(2px); /* Эффект размытия фона */
        }

        /* Кнопка */
        .pdf-load-btn {
            position: relative;
            z-index: 2;
            background-color: #d32f2f; /* Красный цвет, как у PDF иконок */
            color: #fff;
            border: none;
            padding: 18px 32px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pdf-load-btn:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }

        /* Спиннер загрузки */
        .pdf-spinner {
            display: none; /* Скрыт по умолчанию */
            position: relative;
            z-index: 2;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 5px solid #fff;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Класс, который добавляется при загрузке */
        .pdf-lazy-wrapper.is-loading .pdf-load-btn {
            display: none;
        }
        .pdf-lazy-wrapper.is-loading .pdf-spinner {
            display: block;
        }
        
        /* Когда PDF загружен, убираем превью */
        .pdf-lazy-wrapper.loaded {
            background: none !important;
            min-height: auto;
            height: auto;
            display: block;
        }
        .pdf-lazy-wrapper.loaded .pdf-overlay,
        .pdf-lazy-wrapper.loaded .pdf-spinner {
            display: none;
        }
    </style>

    <div class="pdf-lazy-wrapper" id="<?php echo $unique_id; ?>" style="<?php echo $bg_style; ?>">
        
        <div class="pdf-overlay"></div>

        <button class="pdf-load-btn" type="button">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5h3V7h4v5h3l-5 5z"/></svg>
            Открыть руководство
        </button>

        <div class="pdf-spinner"></div>

        <div class="real-pdf-container" style="width: 100%;"></div>

        <template class="pdf-source-code">
            <?php echo $real_embed_code; ?>
        </template>
        
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var wrapper = document.getElementById('<?php echo $unique_id; ?>');
        var btn = wrapper.querySelector('.pdf-load-btn');
        var pdfContainer = wrapper.querySelector('.real-pdf-container');
        
        btn.addEventListener('click', function() {
            // 1. Меняем состояние UI (прячем кнопку, показываем спиннер)
            wrapper.classList.add('is-loading');
            
            // Используем setTimeout, чтобы браузер успел отрисовать спиннер 
            // перед тем, как начнется тяжелая работа JS
            setTimeout(function() {
                var template = wrapper.querySelector('template.pdf-source-code');
                var clone = template.content.cloneNode(true);
                
                // 2. Вставляем HTML плагина в контейнер
                pdfContainer.appendChild(clone);
                
                // 3. Запускаем скрипты
                if (window.jQuery) {
                    var $ = window.jQuery;
                    
                    // Выполняем скрипты плагина
                    $('#<?php echo $unique_id; ?> .real-pdf-container script').each(function() {
                        $.globalEval(this.text || this.textContent || this.innerHTML || '');
                    });

                    // 4. Триггер ресайза и завершение
                    // Даем плагину секунду на инициализацию, затем убираем обложку
                    setTimeout(function(){
                        $(window).trigger('resize');
                        wrapper.classList.add('loaded'); // Убирает фон и спиннер, показывает PDF
                    }, 1500); // 1.5 секунды обычно достаточно, чтобы PDF-viewer появился
                }
            }, 50);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Заменяем шорткод
remove_shortcode('pdf-embedder');
add_shortcode('pdf-embedder', 'lazy_load_pdf_embedder_pro');