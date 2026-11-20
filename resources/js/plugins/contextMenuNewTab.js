/**
 * Глобальный плагин: правый клик мыши открывает действие в новой вкладке
 */
export function initRightClickNewTab(router) {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    document.addEventListener('contextmenu', (event) => {
        const target = event.target;
        if (!target) return;

        // 1. Игнорируем поля ввода и редактируемые блоки (чтобы работали стандартные "Вставить/Копировать")
        if (
            target.closest('input') ||
            target.closest('textarea') ||
            target.closest('select') ||
            target.isContentEditable ||
            target.closest('[contenteditable="true"]')
        ) {
            return;
        }

        // 2. Игнорируем элементы со специальным контекстным меню (например, выбор цен МРК в запчастях)
        if (
            target.closest('[data-context-menu]') ||
            target.closest('.selectable-price') ||
            target.closest('.no-new-tab-context')
        ) {
            return;
        }

        // 3. Ссылки <a> и <router-link>
        const linkEl = target.closest('a[href]');
        if (linkEl) {
            const rawHref = linkEl.getAttribute('href');
            if (rawHref && !rawHref.startsWith('javascript:') && rawHref !== '#') {
                event.preventDefault();
                event.stopPropagation();

                let targetUrl = linkEl.href;
                if (!targetUrl && router) {
                    try {
                        const resolved = router.resolve(rawHref);
                        targetUrl = resolved.href;
                    } catch (e) {
                        targetUrl = rawHref;
                    }
                }

                if (targetUrl) {
                    window.open(targetUrl, '_blank');
                }
                return;
            }
        }

        // 4. Элементы с явным data-href
        const rowWithHref = target.closest('[data-href]');
        if (rowWithHref) {
            event.preventDefault();
            event.stopPropagation();
            const path = rowWithHref.getAttribute('data-href');
            let resolvedUrl = path;
            if (router) {
                try {
                    resolvedUrl = router.resolve(path).href;
                } catch (e) {
                    resolvedUrl = path;
                }
            }
            window.open(resolvedUrl, '_blank');
            return;
        }

        // 5. Строки реестра дел с data-app-id
        const appRow = target.closest('[data-app-id]');
        if (appRow) {
            event.preventDefault();
            event.stopPropagation();
            const appId = appRow.getAttribute('data-app-id');
            const targetPath = `/applications/${appId}`;
            let resolvedUrl = targetPath;
            if (router) {
                try {
                    resolvedUrl = router.resolve(targetPath).href;
                } catch (e) {
                    resolvedUrl = targetPath;
                }
            }
            window.open(resolvedUrl, '_blank');
            return;
        }

        // 6. Кнопки с атрибутом data-route
        const routeBtn = target.closest('[data-route]');
        if (routeBtn) {
            event.preventDefault();
            event.stopPropagation();
            const rPath = routeBtn.getAttribute('data-route');
            let resolvedUrl = rPath;
            if (router) {
                try {
                    resolvedUrl = router.resolve(rPath).href;
                } catch (e) {
                    resolvedUrl = rPath;
                }
            }
            window.open(resolvedUrl, '_blank');
            return;
        }
    }, true);
}

// Экспорт по умолчанию для универсальности
export default initRightClickNewTab;