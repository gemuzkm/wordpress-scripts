/**
 * Оптимизация PDF Embedder: Загрузка по клику (Lazy Load)
 * Заменяет стандартный вывод на превью + кнопку.
 */
function lazy_load_pdf_embedder($atts, $content = null) {
    // 1. Получаем реальный HTML от плагина PDF Embedder
    // Мы вызываем оригинальный шорткод, но пока не выводим его
    $real_embed_code = do_shortcode(shortcode_unautop('[pdf-embedder ' . build_query($atts) . ']'));

    // 2. Генерируем уникальный ID для этого блока
    $unique_id = 'pdf-wrap-' . uniqid();

    // 3. Пытаемся найти красивую обложку
    // Если это страница мануала, берем её миниатюру (Featured Image)
    $cover_image_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
    
    // Если миниатюры нет, ставим серую заглушку или ваше лого
    if (!$cover_image_url) {
        $cover_image_url = 'https://via.placeholder.com/800x600.png?text=Manual+Preview'; 
    }

    // 4. Формируем HTML "Фасада"
    // Тег <template> - это секрет оптимизации. Браузер игнорирует всё, что внутри, пока мы не достанем это через JS.
    ob_start();
    ?>
    <div class="pdf-lazy-wrapper" id="<?php echo $unique_id; ?>" style="position: relative; max-width: 100%; height: auto; background: #f0f0f0; border: 1px solid #ddd;">
        
        <div class="pdf-lazy-preview" style="position: relative; cursor: pointer; display: flex; justify-content: center; align-items: center; min-height: 400px; background-image: url('<?php echo $cover_image_url; ?>'); background-size: cover; background-position: center;">
            <div style="background: rgba(0,0,0,0.6); position: absolute; top:0; left:0; right:0; bottom:0;"></div>
            <button class="pdf-load-btn" style="position: relative; z-index: 2; padding: 15px 30px; font-size: 18px; background: #e74c3c; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                📄 Читать мануал онлайн
            </button>
        </div>

        <template class="pdf-source-code">
            <?php echo $real_embed_code; ?>
        </template>
        
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var wrapper = document.getElementById('<?php echo $unique_id; ?>');
        var btn = wrapper.querySelector('.pdf-lazy-preview');
        
        btn.addEventListener('click', function() {
            // 1. Находим template и берем его содержимое
            var template = wrapper.querySelector('template.pdf-source-code');
            var clone = template.content.cloneNode(true);
            
            // 2. Очищаем превью и вставляем реальный PDF
            wrapper.innerHTML = '';
            wrapper.appendChild(clone);
            
            // 3. ВАЖНО: Заставляем WordPress/jQuery выполнить скрипты, которые были внутри template
            // Простого appendChild недостаточно для выполнения тегов <script> в некоторых браузерах
            // Поэтому используем jQuery, так как плагин PDF Embedder зависит от него
            if (window.jQuery) {
                var $ = window.jQuery;
                // Находим скрипты внутри враппера и перезапускаем их
                 $('#<?php echo $unique_id; ?> script').each(function() {
                    $.globalEval(this.text || this.textContent || this.innerHTML || '');
                });
                
                // 4. Трюк из документации, который вы скинули
                // Сообщаем плагину, что окно изменилось, чтобы он пересчитал размеры Canvas
                setTimeout(function(){
                    $(window).trigger('resize');
                }, 500);
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Регистрируем наш "Ленивый" шорткод
// ВАЖНО: Мы заменяем стандартный шорткод плагина своим!
add_shortcode('pdf-embedder', 'lazy_load_pdf_embedder');