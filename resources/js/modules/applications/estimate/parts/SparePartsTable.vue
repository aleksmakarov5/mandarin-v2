<template>
    <!-- КАРТОЧКА БЛОКА ЗАПЧАСТЕЙ -->
    <div class="estimate-block-card bg-white border rounded shadow-xs overflow-hidden mb-3" @click="onGlobalClick">
        <div class="card-accordion-header px-3 py-2 d-flex justify-content-between align-items-center cursor-pointer select-none"
            @click="isPartsCollapsed = !isPartsCollapsed">
            <!-- Левая часть: квадратная иконка + Заголовок + счётчик + шеврон -->
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="toggleCollapse" role="button" tabindex="0"
                title="Нажмите, чтобы скрыть или показать таблицу запчастей">
                <div class="section-icon-box  icon-parts">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-1.5 section-title-text">
                    <span>Запасные части</span>
                    <span class="text-secondary fw-normal count-text">({{ parts.length }})</span>
                    <!-- Стрелочка индикатора раскрытия/сворачивания -->
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <!-- Правая часть: бейджи сумм СК/ПЗН + Загрузить + История -->
            <div class="d-flex align-items-center gap-2" @click.stop>
                <!-- 2. ПЛАШКИ СУММ НАД ТАБЛИЦЕЙ -->
                <div class="sum-summary-bar d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <!-- Сумма СК (базовая для обоих режимов) -->
                    <div class="summary-pill pill-sk d-flex align-items-center gap-2">
                        <span class="pill-label">Сумма СК:</span>
                        <span class="pill-value font-monospace fw-bold">{{ formatMoney(totalSumSk) }} ₽</span>
                    </div>

                    <!-- ДЛЯ КАСКО: СКИДКА СК И СУММА СО СКИДКОЙ -->
                    <template v-if="isCasco">
                        <div class="summary-pill pill-discount d-flex align-items-center gap-1.5 cursor-pointer"
                            @click.stop="openDiscountModal" title="Нажмите, чтобы изменить процент скидки СК для сметы">
                            <span class="pill-label">Скидка СК:</span>
                            <span class="pill-value font-monospace fw-bold text-primary">{{ cascoDiscount }}%</span>
                            <i class="bi bi-pencil-square ms-1 text-primary" style="font-size: 0.68rem;"></i>
                        </div>

                        <div class="summary-pill pill-casco-half d-flex align-items-center gap-2">
                            <span class="pill-label">Цена со скидкой:</span>
                            <span class="pill-value font-monospace fw-bold text-success">{{
                                formatMoney(totalSumDiscounted)
                            }} ₽</span>
                        </div>
                    </template>

                    <!-- ДЛЯ ОСАГО: СУММА ПЗН И БЕЙДЖ РАЗНИЦЫ -->
                    <template v-else>
                        <div class="summary-pill pill-pzn d-flex align-items-center gap-2"
                            :class="{ 'pill-pzn-diff': isTotalDiff }">
                            <span class="pill-label">Сумма ПЗН:</span>
                            <span class="pill-value font-monospace fw-bold">{{ formatMoney(totalSumPzn) }} ₽</span>
                        </div>

                        <div v-if="isTotalDiff" class="diff-badge font-monospace fw-bold"
                            :class="totalSumPzn > totalSumSk ? 'diff-up' : 'diff-down'">
                            {{ totalSumPzn > totalSumSk ? '+' : '' }}{{ formatMoney(totalSumPzn - totalSumSk) }} ₽
                        </div>
                    </template>
                </div>

                <button type="button"
                    class="btn btn-sm btn-outline-primary btn-upload-sm fw-semibold d-flex align-items-center shadow-xs"
                    @click="onUploadClick" title="Загрузить запчасти из файла калькуляции">
                    <i class="bi bi-upload me-1.5"></i> Загрузить
                </button>

                <button type="button"
                    class="btn btn-sm btn-outline-secondary btn-history-sm fw-semibold d-flex align-items-center shadow-xs"
                    @click="openHistoryModal">
                    <i class="bi bi-clock-history me-1.5 text-primary"></i> История
                </button>
            </div>
        </div>



        <!-- ТЕЛО АККОРДЕОНА (ПЛАШКИ + ТАБЛИЦА + КНОПКИ ПОД НЕЙ) -->
        <div v-show="!isCollapsed" class="accordion-body-wrapper">



            <!-- 3. ТАБЛИЦА: СТРОГО 100% ШИРИНЫ ДЕСКТОПА БЕЗ СКРОЛЛА -->
            <div class="table-desktop-fit-wrapper bg-white border rounded-1 shadow-xs">
                <table class="table table-bordered table-mandarin mb-0 align-middle">

                    <thead>
                        <tr class="header-blue text-center">
                            <th class="col-idx">#</th>
                            <th class="col-name text-start ps-2">Наименование</th>
                            <th class="col-part-num">Кат. номер</th>
                            <th class="col-order-num">Заказ. номер</th>

                            <template v-if="!isStoUser">
                                <th class="col-price">Цена СК</th>

                                <!-- КАСКО: Цена со скидкой (клик открывает скидку СК) -->
                                <th v-if="isCasco" class="col-price col-price-pzn-header text-center"
                                    @click="openDiscountModal" title="Нажмите, чтобы настроить скидку СК">
                                    <div class="d-flex align-items-center justify-content-center gap-1 cursor-pointer">
                                        <span>Со скидкой</span>
                                        <i class="bi bi-percent header-pct-icon"></i>
                                    </div>
                                </th>

                                <!-- ОСАГО: Цена ПЗН (клик открывает модалку процента наценки) -->
                                <th v-else class="col-price col-price-pzn-header text-center"
                                    @click="openPznPercentModal"
                                    title="Нажмите, чтобы увеличить цену ПЗН всех деталей на %">
                                    <div class="d-flex align-items-center justify-content-center gap-1 cursor-pointer">
                                        <span>Цена ПЗН</span>
                                        <i class="bi bi-percent header-pct-icon"></i>
                                    </div>
                                </th>

                                <th class="col-price" title="Двойной клик ЛКМ — выбрать вариант">Оригинал</th>

                                <!-- СТОЛБЦЫ АНАЛОГ И Б/У ТЕПЕРЬ ОСТАЮТСЯ И В КАСКО, И В ОСАГО -->
                                <th class="col-price" title="Двойной клик ЛКМ — выбрать вариант">Аналог</th>
                                <th class="col-price" title="Двойной клик ЛКМ — выбрать вариант">Б/у</th>
                            </template>

                            <th class="col-price" title="Двойной клик ЛКМ — выбрать вариант">Цена СТО</th>

                            <th class="col-shop-link" title="Поиск на ЕвроАвто">ЕА</th>
                            <th class="col-shop-link" title="Поиск на Автопитер">АП</th>
                            <th class="col-shop-link" title="Поиск на Emex">EM</th>

                            <th class="col-comment">Комментарий</th>

                            <template v-if="isNaprTab || isStoUser">
                                <th class="col-status">Статус</th>
                                <th class="col-date">Дата заказа</th>
                                <th class="col-date">Ожид. дата</th>
                            </template>

                            <th v-if="!isStoUser" class="col-trash"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="(part, idx) in parts" :key="part.id" class="spare-part-row" :class="[
                            getRowAuthorClass(part),
                            { 'row-small-parts': part.is_small_parts }
                        ]">
                            <!-- 1. # Номер строки с тултипом автора -->
                            <td class="text-center font-monospace cell-index p-0">
                                <span class="badge-index" :class="{ 'badge-manual': isManualRow(part) }"
                                    @mouseenter="handleCreatorMouseEnter($event, part)"
                                    @mouseleave="handleCreatorMouseLeave">
                                    {{ idx + 1 }}
                                </span>
                            </td>

                            <!-- 2. Наименование -->
                            <td class="editable-cell text-start fw-semibold text-truncate col-name ps-2"
                                :contenteditable="!part.is_small_parts" :title="part.name"
                                @blur="saveTextField(part, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ part.name }}
                            </td>

                            <!-- 3. Кат. номер -->
                            <td class="editable-cell text-center font-monospace text-uppercase col-part-num"
                                :contenteditable="!part.is_small_parts"
                                @blur="saveTextField(part, 'part_number', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ part.part_number }}
                            </td>

                            <!-- 4. Заказ. номер -->
                            <td class="editable-cell text-center font-monospace text-uppercase col-order-num"
                                :contenteditable="!part.is_small_parts"
                                :title="part.order_number || 'Номер заказа не указан'"
                                @blur="saveTextField(part, 'order_number', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ part.order_number || '—' }}
                            </td>

                            <!-- ЦЕНЫ КАЛЬКУЛЯЦИИ -->
                            <template v-if="!isStoUser">
                                <!-- Цена СК -->
                                <td class="cell-dark text-end font-monospace text-white editable-cell"
                                    contenteditable="true" @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_ic', 'Цена СК', null, $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoney(part.price_ic ?? part.price_ic_full); $event.target.blur()"
                                    @blur="savePriceIcField(part, $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()">
                                    {{ formatMoney(part.price_ic ?? part.price_ic_full) }}
                                </td>

                                <!-- КАСКО: Цена со скидкой (price_ic_half) -->
                                <td v-if="isCasco" class="cell-dark text-end font-monospace text-white editable-cell"
                                    :class="{ 'casco-half-highlight': isCascoDiscounted(part) }" contenteditable="true"
                                    @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_ic_half', 'Цена со скидкой', null, $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoney(part.price_ic_half); $event.target.blur()"
                                    @blur="saveMoneyField(part, 'price_ic_half', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()">
                                    {{ formatMoney(part.price_ic_half) }}
                                </td>

                                <!-- ОСАГО: Цена ПЗН (ВЫДЕЛЕНИЕ ЯРКО-ЖЕЛТЫМ ЦВЕТОМ, ЕСЛИ НЕ РАВНА СК) -->
                                <td v-else class="text-end font-monospace editable-cell"
                                    :class="isPznDiff(part) ? 'cell-pzn-diff-yellow' : 'cell-dark text-white'"
                                    contenteditable="true" @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_pzn', 'Цена ПЗН', null, $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoney(part.price_pzn ?? part.price_sto); $event.target.blur()"
                                    @blur="saveMoneyField(part, 'price_pzn', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()">
                                    {{ formatMoney(part.price_pzn ?? part.price_sto) }}
                                </td>

                                <!-- Оригинал -->
                                <td class="text-end font-monospace editable-cell selectable-price"
                                    :class="{ 'selected-mrk-active': part.selected_mrk === 'original' || part.selected_price === 1 }"
                                    contenteditable="true" @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_original', 'Оригинал', 'original', $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoneyOrEmpty(part.price_original); $event.target.blur()"
                                    @blur="saveMoneyField(part, 'price_original', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    title="Одиночный клик — калькулятор, двойной — выбор МРК">
                                    {{ formatMoneyOrEmpty(part.price_original) }}
                                </td>

                                <!-- Аналог (есть и в КАСКО, и в ОСАГО) -->
                                <td class="text-end font-monospace editable-cell selectable-price"
                                    :class="{ 'selected-mrk-active': part.selected_mrk === 'analogue' || part.selected_price === 2 }"
                                    contenteditable="true" @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_analogue', 'Аналог', 'analogue', $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoneyOrEmpty(part.price_analogue); $event.target.blur()"
                                    @blur="saveMoneyField(part, 'price_analogue', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    title="Одиночный клик — калькулятор, двойной — выбор МРК">
                                    {{ formatMoneyOrEmpty(part.price_analogue) }}
                                </td>

                                <!-- Б/у (есть и в КАСКО, и в ОСАГО) -->
                                <td class="text-end font-monospace editable-cell selectable-price"
                                    :class="{ 'selected-mrk-active': part.selected_mrk === 'used' || part.selected_price === 3 }"
                                    contenteditable="true" @focus="selectAllCellContent($event)"
                                    @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_used', 'Б/у', 'used', $event)"
                                    @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                    @keydown.esc="$event.target.innerText = formatMoneyOrEmpty(part.price_used); $event.target.blur()"
                                    @blur="saveMoneyField(part, 'price_used', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    title="Одиночный клик — калькулятор, двойной — выбор МРК">
                                    {{ formatMoneyOrEmpty(part.price_used) }}
                                </td>
                            </template>

                            <!-- Цена СТО -->
                            <td class="text-end font-monospace editable-cell selectable-price"
                                :class="{ 'selected-mrk-active': part.selected_mrk === 'sto' || part.selected_price === 4 }"
                                contenteditable="true" @focus="selectAllCellContent($event)"
                                @click="selectAllCellContent($event); handlePriceCellClick(part, 'price_sto', 'Цена СТО', !isStoUser ? 'sto' : null, $event)"
                                @keydown="onNumberKeydown" @paste.prevent="onNumberPaste"
                                @keydown.esc="$event.target.innerText = formatMoneyOrEmpty(part.price_sto); $event.target.blur()"
                                @blur="saveMoneyField(part, 'price_sto', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoneyOrEmpty(part.price_sto) }}
                            </td>

                            <!-- Ссылки на ЕвроАвто, Автопитер, Emex -->
                            <td class="text-center p-0 cell-link">
                                <a v-if="hasValidPartNumber(part)" :href="getEuroAutoUrl(part.part_number)"
                                    target="_blank" class="shop-btn-link ea-link" title="Искать деталь на ЕвроАвто">
                                    -&gt;
                                </a>
                                <span v-else class="text-muted" style="font-size: 0.65rem;">—</span>
                            </td>

                            <td class="text-center p-0 cell-link">
                                <a v-if="hasValidPartNumber(part)" :href="getAutopiterUrl(part.part_number)"
                                    target="_blank" class="shop-btn-link ap-link" title="Искать деталь на Автопитер">
                                    -&gt;
                                </a>
                                <span v-else class="text-muted" style="font-size: 0.65rem;">—</span>
                            </td>

                            <td class="text-center p-0 cell-link">
                                <a v-if="hasValidPartNumber(part)" :href="getEmexUrl(part.part_number)" target="_blank"
                                    class="shop-btn-link em-link" title="Искать деталь на Emex">
                                    -&gt;
                                </a>
                                <span v-else class="text-muted" style="font-size: 0.65rem;">—</span>
                            </td>

                            <!-- Комментарий (чат по клику) -->
                            <td class="comment-cell text-truncate px-1" @click.stop="openChatModal(part)"
                                :title="getLatestCommentText(part) || 'Нажмите, чтобы открыть переписку'">
                                <div
                                    class="d-flex align-items-center justify-content-between gap-1 w-100 overflow-hidden">
                                    <span v-if="getLatestCommentText(part)" class="comment-preview text-truncate">
                                        {{ getLatestCommentText(part) }}
                                    </span>
                                    <span v-else class="text-muted comment-placeholder">+ заметка</span>

                                    <span v-if="getCommentsCount(part) > 1"
                                        class="badge bg-secondary-subtle text-secondary rounded-pill px-1 flex-shrink-0"
                                        style="font-size: 0.60rem;">
                                        {{ getCommentsCount(part) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Столбцы вкладки Направления -->
                            <template v-if="isNaprTab || isStoUser">
                                <!-- Статус запчасти с ПОДТВЕРЖДЕНИЕМ СМЕНЫ -->
                                <td class="p-0 text-center cell-status-wrapper"
                                    @click.stop="activateStatusSelect(part.id)">
                                    <select v-if="editingStatusId === part.id" ref="statusSelectRef"
                                        class="form-select form-select-micro" :value="part.status || 'В поиске'"
                                        @change="onStatusSelectChange(part, $event.target.value)"
                                        @blur="editingStatusId = null">
                                        <option v-for="st in statusOptions" :key="st" :value="st">{{ st }}</option>
                                    </select>

                                    <div v-else class="status-theme-pill text-truncate"
                                        :class="getStatusClass(part.status)"
                                        title="Нажмите для смены статуса (с подтверждением)">
                                        {{ part.status || 'В ПОИСКЕ' }}
                                    </div>
                                </td>

                                <!-- Дата заказа (только для чтения!) -->
                                <td class="text-center font-monospace cell-readonly-date p-0"
                                    title="Выставляется автоматически">
                                    {{ formatDateDisplay(part.order_date) }}
                                </td>

                                <!-- Ожидаемая дата поставки -->
                                <td class="text-center font-monospace cell-delivery-date p-0 position-relative"
                                    title="Кликните для выбора даты поставки">
                                    <div class="delivery-date-display">
                                        <span v-if="part.delivery_date" class="date-text">
                                            {{ formatDateDisplay(part.delivery_date) }}
                                        </span>
                                        <span v-else class="date-empty-placeholder">
                                            + дата
                                        </span>
                                    </div>
                                    <!-- Скрытый нативный инпут на всю ячейку, вызываемый кликом -->
                                    <input type="date" class="hidden-date-picker-input"
                                        :value="formatDateForInput(part.delivery_date)"
                                        @change="saveTextField(part, 'delivery_date', $event.target.value)" />
                                </td>
                            </template>

                            <!-- Удаление -->
                            <td v-if="!isStoUser" class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-secondary btn-trash-micro p-0"
                                    title="Удалить позицию" @click="confirmDelete(part)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="parts.length === 0">
                            <td :colspan="tableColspan" class="text-center py-4 text-muted small">
                                В смете нет запчастей. Нажмите «+ Добавить запчасть» ниже.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 4. КНОПКИ ДЕЙСТВИЙ ПОД ТАБЛИЦЕЙ -->
            <div class="table-bottom-actions mt-2 d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center shadow-xs"
                    @click="addNewRow">
                    <i class="bi bi-plus-circle me-1.5"></i> Добавить запчасть
                </button>

                <button v-if="!isStoUser" type="button"
                    class="btn btn-sm btn-small-parts-sm fw-bold d-flex align-items-center shadow-xs"
                    @click="handleSmallPartsClick"
                    :title="isCasco ? 'Рассчитать 2% от суммы со скидкой' : 'Рассчитать 2% от суммы калькуляции'">
                    <i class="bi bi-percent me-1.5"></i> Мелкие детали (2%)
                </button>
            </div>

            <!-- МОДАЛЬНОЕ ОКНО ЧАТА / КОММЕНТАРИЕВ ДЕТАЛИ -->
            <Teleport to="body">
                <div v-if="activeChatPart" class="modal-backdrop-custom" @click="closeChatModal">
                    <div class="modal-dialog-chat shadow-lg" @click.stop>
                        <div
                            class="chat-modal-header d-flex justify-content-between align-items-center p-2.5 px-3 border-bottom bg-light">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <i class="bi bi-chat-left-text-fill text-primary fs-5"></i>
                                <div class="text-truncate">
                                    <div class="fw-bold small text-dark text-truncate">{{ activeChatPart.name }}
                                    </div>
                                    <div class="text-muted font-monospace" style="font-size: 0.68rem;">
                                        Кат. №: {{ activeChatPart.part_number || 'не указан' }}
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-close" @click="closeChatModal"></button>
                        </div>

                        <div ref="chatContainerRef" class="chat-messages-container p-3 d-flex flex-column gap-2.5">
                            <div v-if="currentPartComments.length === 0" class="text-center text-muted py-4 small">
                                По этой детали ещё нет комментариев. Напишите первый ниже.
                            </div>

                            <div v-for="comm in currentPartComments" :key="comm.id" class="chat-bubble-wrapper"
                                :class="{ 'chat-bubble-outgoing': isMyComment(comm) }">
                                <div class="chat-bubble shadow-xs">
                                    <div
                                        class="chat-bubble-meta d-flex justify-content-between align-items-center gap-2 mb-1">
                                        <span class="chat-author fw-bold">
                                            {{ comm.user ? (comm.user.last_name || comm.user.name) : 'Пользователь'
                                            }}
                                        </span>
                                        <span class="chat-time text-muted font-monospace">
                                            {{ formatDateTime(comm.created_at) }}
                                        </span>
                                    </div>
                                    <div class="chat-text">
                                        {{ comm.comment }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chat-input-bar p-2.5 bg-light border-top d-flex gap-2 align-items-center">
                            <textarea v-model="newCommentText" class="form-control form-control-sm chat-textarea"
                                rows="1" placeholder="Напишите комментарий (Enter для отправки)..."
                                @keydown.enter.exact.prevent="sendComment"></textarea>
                            <button type="button" class="btn btn-primary btn-sm px-3 fw-bold flex-shrink-0"
                                :disabled="!newCommentText.trim() || isSendingComment" @click="sendComment">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- ПАРЯЩИЙ ТУЛТИП АВТОРА СОЗДАНИЯ -->
            <Teleport to="body">
                <div v-if="creatorTooltip.visible" class="floating-creator-tooltip shadow-lg"
                    :style="{ top: creatorTooltip.y + 'px', left: creatorTooltip.x + 'px' }">
                    <div class="fw-bold mb-0.5"><i class="bi bi-person-fill me-1 text-warning"></i>Автор создания:
                    </div>
                    <div>{{ creatorTooltip.author }}</div>
                    <div class="text-muted font-monospace mt-0.5" style="font-size: 0.65rem;">{{ creatorTooltip.date
                        }}
                    </div>
                </div>
            </Teleport>

            <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ ИЗМЕНЕНИЙ -->
            <Teleport to="body">
                <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                    <div class="modal-dialog-custom shadow-lg" @click.stop>
                        <div
                            class="modal-header-custom d-flex justify-content-between align-items-center p-2.5 px-3 border-bottom bg-light">
                            <h6 class="m-0 fw-bold d-flex align-items-center fs-6">
                                <i class="bi bi-clock-history me-2 text-primary"></i>
                                История изменений по запчастям
                            </h6>
                            <button type="button" class="btn-close" @click="showHistoryModal = false"></button>
                        </div>
                        <div class="modal-body-custom p-3" style="max-height: 480px; overflow-y: auto;">
                            <div v-if="historyLoading" class="text-center py-4 text-muted">
                                <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                                Загрузка...
                            </div>
                            <div v-else-if="historyList.length === 0" class="text-center py-4 text-muted small">
                                История изменений пока пуста.
                            </div>
                            <table v-else class="table table-sm table-striped history-table mb-0 align-middle">
                                <thead>
                                    <tr class="small text-muted border-bottom">
                                        <th style="width: 125px;">Дата / Время</th>
                                        <th style="width: 130px;">Сотрудник</th>
                                        <th style="width: 130px;">Поле / Действие</th>
                                        <th>Было</th>
                                        <th>Стало</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="h in historyList" :key="h.id" class="small">
                                        <td class="font-monospace text-nowrap">{{ formatDateTime(h.created_at) }}
                                        </td>
                                        <td class="fw-semibold">
                                            {{ h.user ? (h.user.last_name || h.user.name) : 'Система' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                {{ translateField(h.field_name || h.action_type) }}
                                            </span>
                                        </td>
                                        <td class="text-danger text-truncate font-monospace" style="max-width: 140px;">
                                            {{ h.old_value || '—' }}
                                        </td>
                                        <td class="text-success text-truncate font-monospace fw-semibold"
                                            style="max-width: 140px;">
                                            {{ h.new_value || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- МОДАЛЬНОЕ ОКНО МАССОВОГО УВЕЛИЧЕНИЯ ЦЕН ПЗН НА % (ОСАГО) -->
            <Teleport to="body">
                <div v-if="showPznPercentModal" class="modal-backdrop-custom" @click="closePznPercentModal">
                    <div class="modal-dialog-percent shadow-lg" @click.stop>
                        <div
                            class="modal-header-custom d-flex justify-content-between align-items-center p-2.5 px-3 border-bottom bg-light">
                            <h6 class="m-0 fw-bold d-flex align-items-center fs-6 text-dark">
                                <i class="bi bi-graph-up-arrow me-2 text-primary"></i>
                                Увеличить Цену ПЗН на %
                            </h6>
                            <button type="button" class="btn-close btn-close-xs" @click="closePznPercentModal"></button>
                        </div>

                        <div class="p-3">
                            <div class="text-muted small mb-2">
                                Цены ПЗН всех деталей будут рассчитаны как: <br>
                                <strong class="text-dark font-monospace">Цена ПЗН = Цена СК + указанный %</strong>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-1 text-dark">Процент увеличения
                                    (%):</label>
                                <div class="input-group input-group-sm">
                                    <input ref="pznPercentInputRef" v-model="pznPercentValue" type="text"
                                        class="form-control font-monospace fw-bold fs-6 text-center"
                                        placeholder="Например: 10" @keydown="onNumberKeydown"
                                        @paste.prevent="onNumberPaste" @keydown.enter.prevent="applyPznPercent" />
                                    <span class="input-group-text font-monospace fw-bold bg-light">%</span>
                                </div>
                            </div>

                            <div class="d-flex gap-1.5 justify-content-center mb-3">
                                <button v-for="preset in [5, 10, 15, 20]" :key="preset" type="button"
                                    class="btn btn-xs btn-outline-secondary font-monospace"
                                    @click="pznPercentValue = String(preset)">
                                    +{{ preset }}%
                                </button>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-light border px-3"
                                    @click="closePznPercentModal">
                                    Отмена
                                </button>
                                <button type="button"
                                    class="btn btn-sm btn-primary fw-bold px-3 d-flex align-items-center"
                                    :disabled="!isPznPercentValid || isApplyingPercent" @click="applyPznPercent">
                                    <span v-if="isApplyingPercent"
                                        class="spinner-border spinner-border-sm me-1.5"></span>
                                    <span>Применить к смете</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- МОДАЛЬНОЕ ОКНО СКИДКИ КАСКО ДЛЯ ДЕЛА -->
            <Teleport to="body">
                <div v-if="showDiscountModal" class="modal-backdrop-custom" @click="closeDiscountModal">
                    <div class="modal-dialog-percent shadow-lg" @click.stop>
                        <div
                            class="modal-header-custom d-flex justify-content-between align-items-center p-2.5 px-3 border-bottom bg-light">
                            <h6 class="m-0 fw-bold d-flex align-items-center fs-6 text-dark">
                                <i class="bi bi-tag-fill me-2 text-primary"></i>
                                Скидка СК по делу (КАСКО)
                            </h6>
                            <button type="button" class="btn-close btn-close-xs" @click="closeDiscountModal"></button>
                        </div>

                        <div class="p-3">
                            <div class="text-muted small mb-2">
                                Согласованный процент скидки СК. <br>
                                Все цены со скидкой и мелкие детали будут пересчитаны.
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-1 text-dark">Процент скидки СК
                                    (%):</label>
                                <div class="input-group input-group-sm">
                                    <input ref="discountInputRef" v-model="tempDiscountValue" type="text"
                                        class="form-control font-monospace fw-bold fs-6 text-center"
                                        placeholder="Например: 15" @keydown="onNumberKeydown"
                                        @paste.prevent="onNumberPaste" @keydown.enter.prevent="applyCascoDiscount" />
                                    <span class="input-group-text font-monospace fw-bold bg-light">%</span>
                                </div>
                            </div>

                            <div class="d-flex gap-1.5 justify-content-center mb-3">
                                <button v-for="preset in [0, 5, 10, 15, 20]" :key="preset" type="button"
                                    class="btn btn-xs btn-outline-secondary font-monospace"
                                    @click="tempDiscountValue = String(preset)">
                                    {{ preset }}%
                                </button>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-light border px-3"
                                    @click="closeDiscountModal">
                                    Отмена
                                </button>
                                <button type="button"
                                    class="btn btn-sm btn-primary fw-bold px-3 d-flex align-items-center"
                                    :disabled="!isDiscountValid || isSavingDiscount" @click="applyCascoDiscount">
                                    <span v-if="isSavingDiscount"
                                        class="spinner-border spinner-border-sm me-1.5"></span>
                                    <span>Применить</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- ВСПЛЫВАЮЩИЙ ИНТЕРАКТИВНЫЙ КАЛЬКУЛЯТОР ДЛЯ ЦЕНЫ -->
            <Teleport to="body">
                <InlinePriceCalculator v-if="activeCalc.visible && activeCalc.part"
                    :key="`${activeCalc.part.id}_${activeCalc.field}`" :initial-value="activeCalc.initialValue"
                    :title="activeCalc.title" :target-rect="activeCalc.targetRect"
                    :target-element="activeCalc.targetElement" @apply="applyCalcResult" @close="closePriceCalc" />
            </Teleport>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import InlinePriceCalculator from './InlinePriceCalculator.vue';

const props = defineProps({
    applicationId: {
        type: Number,
        required: true,
    },
    statusId: {
        type: [Number, String],
        default: null,
    },
    application: {
        type: Object,
        default: () => ({}),
    },
    currentTab: {
        type: String,
        default: 'requests',
    },
});

const emit = defineEmits(['updated']);

// Состояние аккордеона (свернуть/развернуть блок запчастей)
const isCollapsed = ref(false);

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};

// Функция вычисления CSS-класса цвета строки
const getRowAuthorClass = (part) => {
    const type = Number(part.author_type ?? 0);
    switch (type) {
        case 1:
            return 'row-mrk';      // МРК - синий/голубой
        case 2:
            return 'row-mentor';   // Наставник - сиреневый
        case 3:
            return 'row-spm';      // Запчастист - зеленый
        case 4:
            return 'row-sto';      // Сервис / СТО - желтый
        case 5:
            return 'row-other';    // Другой пользователь - светло-коричневый
        case 6:
            return 'row-ai';       // ИИ анализ фото - розовый
        case 0:
        default:
            return 'row-auto-calc'; // Из калькуляции - белый
    }
};

// Единый стейт для одиночного/двойного клика и калькулятора
const clickTapState = reactive({
    timer: null,
    lastTime: 0,
    lastPartId: null,
    lastType: null,
});

onUnmounted(() => {
    if (clickTapState.timer) {
        clearTimeout(clickTapState.timer);
        clickTapState.timer = null;
    }
});

// Состояние inline-калькулятора
const activeCalc = reactive({
    visible: false,
    part: null,
    field: null,
    title: '',
    initialValue: 0,
    targetRect: { top: 0, left: 0, bottom: 0, right: 0 },
    targetElement: null,
});

// Функция надежного парсинга числа из любого источника
const parsePriceValue = (val) => {
    if (val === null || val === undefined) return 0;
    const clean = String(val)
        .replace(/\s+/g, '')
        .replace(/\u00a0/g, '')
        .replace(',', '.');
    const num = parseFloat(clean);
    return isNaN(num) ? 0 : num;
};

// Открытие калькулятора
const openPriceCalc = (part, field, title, element) => {
    if (!element) return;
    const rect = element.getBoundingClientRect();

    const domText = element.innerText ? element.innerText.trim() : '';
    const resolvedValue = domText !== '' ? parsePriceValue(domText) : parsePriceValue(part[field]);

    activeCalc.part = part;
    activeCalc.field = field;
    activeCalc.title = `${title}: ${part.name || 'деталь'}`;
    activeCalc.initialValue = resolvedValue;
    activeCalc.targetRect = {
        top: rect.top,
        left: rect.left,
        bottom: rect.bottom,
        right: rect.right,
    };
    activeCalc.targetElement = element;
    activeCalc.visible = true;
};

// Применение результата калькулятора
const applyCalcResult = async (newVal) => {
    if (activeCalc.part && activeCalc.field) {
        const part = activeCalc.part;
        const field = activeCalc.field;
        activeCalc.visible = false;
        if (field === 'price_ic') {
            await savePriceIcField(part, newVal);
        } else {
            await saveMoneyField(part, field, newVal);
        }
    }
};

const closePriceCalc = () => {
    activeCalc.visible = false;
};

const parts = ref([]);
const isCasco = ref(false);
const cascoDiscount = ref(0);
const isStoUser = ref(false);
const serverStatusId = ref(0);

// Модальное окно скидки КАСКО
const showDiscountModal = ref(false);
const tempDiscountValue = ref('0');
const isSavingDiscount = ref(false);
const discountInputRef = ref(null);

const isDiscountValid = computed(() => {
    const val = parseFloat(String(tempDiscountValue.value).replace(',', '.'));
    return !isNaN(val) && val >= 0 && val <= 99;
});

const openDiscountModal = () => {
    tempDiscountValue.value = String(cascoDiscount.value ?? 0);
    showDiscountModal.value = true;
    nextTick(() => {
        discountInputRef.value?.focus();
        discountInputRef.value?.select();
    });
};

const closeDiscountModal = () => {
    showDiscountModal.value = false;
};

// Статусы запчастей
const statusOptions = [
    'В поиске',
    'Заказана',
    'Ожидается доставка',
    'На складе',
    'Выдана на СТО',
    'Установлена',
    'Отказ',
];
const editingStatusId = ref(null);
const statusSelectRef = ref(null);

// Чат / Комментарии
const activeChatPart = ref(null);
const newCommentText = ref('');
const isSendingComment = ref(false);
const chatContainerRef = ref(null);

// Тултип автора
const creatorTooltip = reactive({
    visible: false,
    author: '',
    date: '',
    x: 0,
    y: 0,
});

// История аудита
const showHistoryModal = ref(false);
const historyLoading = ref(false);
const historyList = ref([]);

// Суммы калькуляции
const totalSumSk = computed(() => {
    return parts.value.reduce((acc, p) => acc + (parseFloat(p.price_ic ?? p.price_ic_full) || 0), 0);
});

const totalSumDiscounted = computed(() => {
    return parts.value.reduce((acc, p) => acc + (parseFloat(p.price_ic_half) || 0), 0);
});

const totalSumPzn = computed(() => {
    return parts.value.reduce((acc, p) => acc + (parseFloat(p.price_pzn ?? p.price_sto) || 0), 0);
});

const isTotalDiff = computed(() => {
    return Math.abs(totalSumSk.value - totalSumPzn.value) > 0.01;
});

// Проверка: вкладка Направления
const isNaprTab = computed(() => {
    const stId = Number(
        props.statusId ??
        props.application?.id_status ??
        props.application?.status_id ??
        serverStatusId.value ??
        0
    );
    if (stId >= 11) return true;

    const tab = String(props.currentTab || '').toLowerCase();
    return tab === 'applications' || tab === 'napr' || tab === 'directions';
});

const tableColspan = computed(() => {
    /*
     * Базовые колонки:
     * #, Наименование, Кат. номер, Заказ. номер
     */
    let cols = 4;

    /*
     * Для обычного пользователя:
     * Цена СК, Цена ПЗН/Со скидкой, Оригинал, Аналог, Б/у
     */
    if (!isStoUser.value) {
        cols += 5;
    }

    /*
     * Цена СТО, ЕА, АП, EM, Комментарий
     */
    cols += 1; // Цена СТО
    cols += 3; // ЕА, АП, EM
    cols += 1; // Комментарий

    /*
     * Статус, Дата заказа, Ожид. дата
     */
    if (isNaprTab.value || isStoUser.value) {
        cols += 3;
    }

    /*
     * Удаление
     */
    if (!isStoUser.value) {
        cols += 1;
    }

    return cols;
});

// Загрузка запчастей
const fetchParts = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/spare-parts`);
        parts.value = res.data.data || [];
        isCasco.value = Boolean(res.data.is_casco);
        cascoDiscount.value = Number(res.data.discount_percent ?? res.data.iznos ?? 0);
        isStoUser.value = Boolean(res.data.is_sto);
        if (res.data.status_id) {
            serverStatusId.value = Number(res.data.status_id);
        }
    } catch (err) {
        console.error('Ошибка загрузки запчастей:', err);
    }
};

// Применение скидки КАСКО ко всей смете
const applyCascoDiscount = async () => {
    if (!isDiscountValid.value || isSavingDiscount.value) return;

    const discountVal = parseFloat(String(tempDiscountValue.value).replace(',', '.'));
    isSavingDiscount.value = true;

    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/spare-parts/casco-discount`, {
            discount: discountVal,
        });

        cascoDiscount.value = discountVal;
        if (res.data?.data) {
            parts.value = res.data.data;
        }
        closeDiscountModal();
        emit('updated');
    } catch (err) {
        console.error('Ошибка сохранения скидки КАСКО:', err);
        // Резервный вызов через прямое обновление поля дела, если роут не зарегистрирован
        try {
            await axios.put(`/api/applications/${props.applicationId}`, {
                discount_percent: discountVal,
            });
            await fetchParts();
            closeDiscountModal();
            emit('updated');
        } catch (fallbackErr) {
            alert('Не удалось обновить скидку КАСКО: ' + (err.response?.data?.message || err.message));
        }
    } finally {
        isSavingDiscount.value = false;
    }
};

// Проверка: строка КАСКО имеет скидку
const isCascoDiscounted = (part) => {
    const base = parseFloat(part.price_ic ?? part.price_ic_full) || 0;
    const half = parseFloat(part.price_ic_half) || 0;
    return base > 0 && half > 0 && Math.abs(base - half) > 0.01;
};

// Сохранение базовой цены СК с автопересчётом для КАСКО
const savePriceIcField = async (part, rawValue) => {
    const cleanStr = String(rawValue || '').replace(/\s+/g, '').replace(/\u00a0/g, '').replace(',', '.');
    const newBase = parseFloat(cleanStr) || 0;
    const currentBase = parseFloat(part.price_ic ?? part.price_ic_full) || 0;

    if (Math.abs(newBase - currentBase) < 0.0001) return;

    part.price_ic = newBase;
    part.price_ic_full = newBase;

    // В режиме КАСКО пересчитываем цену со скидкой
    if (isCasco.value) {
        const multiplier = (100 - (cascoDiscount.value || 0)) / 100;
        part.price_ic_half = Math.round(newBase * multiplier * 100) / 100;
    }

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/field`, {
            field: 'price_ic',
            value: newBase,
            price_ic_half: part.price_ic_half,
        });
        if (res.data?.data) {
            Object.assign(part, res.data.data);
        }
        if (res.data?.updated_small_part) {
            const small = res.data.updated_small_part;
            const idx = parts.value.findIndex((p) => p.id === small.id || p.is_small_parts);
            if (idx !== -1) {
                parts.value[idx].price_ic = small.price_ic;
                parts.value[idx].price_ic_full = small.price_ic_full ?? small.price_ic;
                parts.value[idx].price_ic_half = small.price_ic_half; // <-- обновляем цену со скидкой
                parts.value[idx].price_pzn = small.price_pzn;
                parts.value[idx].price_sto = small.price_sto ?? small.price_pzn;
            }
        }
        emit('updated');
    } catch (err) {
        console.error('Ошибка сохранения цены СК:', err);
    }
};

// Заглушка под будущую загрузку из файла
const onUploadClick = () => {
    alert('Функционал загрузки калькуляции из файла будет подключен на следующем шаге.');
};

// Добавление строки
const addNewRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/spare-parts`, {
            name: 'Новая деталь',
            part_number: '',
            price_ic: 0,
            price_pzn: 0,
        });

        if (res.data?.data) {
            parts.value.push(res.data.data);
        }

        // Если бэкенд вернул пересчитанные мелкие детали — сразу обновляем их в таблице!
        if (res.data?.updated_small_part) {
            const small = res.data.updated_small_part;
            const idx = parts.value.findIndex((p) => p.id === small.id || p.is_small_parts);
            if (idx !== -1) {
                parts.value[idx].price_ic = small.price_ic;
                parts.value[idx].price_ic_full = small.price_ic_full ?? small.price_ic;
                parts.value[idx].price_ic_half = small.price_ic_half;
                parts.value[idx].price_pzn = small.price_pzn;
                parts.value[idx].price_sto = small.price_sto ?? small.price_pzn;
                parts.value[idx].iznos = small.iznos;
            }
        }

        emit('updated');
    } catch (err) {
        console.error('Ошибка добавления строки:', err);
    }
};

const normalizeText = (val) => {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/\u00a0/g, ' ')  // Заменяем &nbsp; на обычный пробел
        .replace(/\r?\n|\r/g, ' ') // Убираем случайные переносы строк из contenteditable
        .trim();
};

// Сохранение текста
const saveTextField = async (part, field, rawValue) => {
    let cleanVal = normalizeText(rawValue);

    // Обрезка до 10 символов — СТРОГО ТОЛЬКО ДЛЯ ДАТ!
    if (field === 'delivery_date' || field === 'order_date') {
        cleanVal = cleanVal ? cleanVal.substring(0, 10) : null;
        const currentVal = part[field] ? String(part[field]).substring(0, 10) : null;
        if (cleanVal === currentVal) return;
    } else {
        // Для текстовых полей (name, part_number и т.д.) сравниваем ВСЮ строку целиком!
        const currentVal = normalizeText(part[field]);
        if (cleanVal === currentVal) return;
    }

    const previousValue = part[field];
    part[field] = cleanVal;

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/field`, {
            field,
            value: cleanVal,
        });
        if (res.data?.data) {
            Object.assign(part, res.data.data);
            part.author_type = Number(res.data.data.author_type);
            part.updated_by_user_id = res.data.data.updated_by_user_id;
            if (res.data.data.status) part.status = res.data.data.status;
            if (res.data.data.order_date !== undefined) part.order_date = res.data.data.order_date;
        }
        emit('updated');
    } catch (err) {
        console.error(`Ошибка сохранения ${field}:`, err);
        part[field] = previousValue;
    }
};

// Запрет нечислового ввода в цены
const onNumberKeydown = (event) => {
    const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
        'Tab', 'Home', 'End', 'Enter'
    ];
    if (allowedKeys.includes(event.key) || event.ctrlKey || event.metaKey) return;
    if (/^[0-9]$/.test(event.key)) return;
    if (event.key === '.' || event.key === ',') {
        const currentText = event.target.innerText || '';
        if (!currentText.includes('.') && !currentText.includes(',')) return;
    }
    event.preventDefault();
};

const onNumberPaste = (event) => {
    const text = (event.clipboardData || window.clipboardData).getData('text');
    const cleanNumber = text.replace(/[^0-9.,]/g, '').replace(',', '.');
    if (cleanNumber) {
        document.execCommand('insertText', false, cleanNumber);
    }
};

// Сохранение числовых и денежных полей
const saveMoneyField = async (part, field, rawValue) => {
    const cleanStr = String(rawValue || '')
        .replace(/\s+/g, '')
        .replace(/\u00a0/g, '')
        .replace(',', '.');
    let cleanVal = parseFloat(cleanStr) || 0;

    // ПРАВИЛО: при обнулении цены ПЗН (ввели 0, пусто или стерли) — приравниваем к цене СК
    if (field === 'price_pzn' && cleanVal <= 0) {
        cleanVal = parseFloat(part.price_ic ?? part.price_ic_full) || 0;
    }

    const currentVal = parseFloat(part[field]) || 0;

    if (Math.abs(cleanVal - currentVal) < 0.0001) {
        if (field === 'price_pzn') {
            part.price_pzn = cleanVal;
        }
        return;
    }

    const previousValue = part[field];
    part[field] = cleanVal;

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/field`, {
            field,
            value: cleanVal,
        });
        if (res.data?.data) {
            Object.assign(part, res.data.data);
        }
        if (res.data?.updated_small_part) {
            const small = res.data.updated_small_part;
            const idx = parts.value.findIndex((p) => p.id === small.id || p.is_small_parts);
            if (idx !== -1) {
                parts.value[idx].price_ic = small.price_ic;
                parts.value[idx].price_ic_full = small.price_ic_full ?? small.price_ic;
                parts.value[idx].price_ic_half = small.price_ic_half; // <-- обновляем цену со скидкой
                parts.value[idx].price_pzn = small.price_pzn;
                parts.value[idx].price_sto = small.price_sto ?? small.price_pzn;
            }
        }
        emit('updated');
    } catch (err) {
        console.error(`Ошибка сохранения ${field}:`, err);
        part[field] = previousValue;
    }
};

// Двойной клик ЛКМ: выбор варианта МРК
const toggleMrkSelection = async (part, type) => {
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/toggle-mrk`, {
            type,
        });
        if (res.data?.data) {
            // Применяем все свежие данные с бэкенда, включая author_type!
            Object.assign(part, res.data.data);
            part.author_type = Number(res.data.data.author_type);
            part.updated_by_user_id = res.data.data.updated_by_user_id;
        }
        emit('updated');
    } catch (err) {
        console.error('Ошибка выбора варианта МРК:', err);
    }
};

// Проверка отличия цены ПЗН от СК
const isPznDiff = (part) => {
    const ic = parseFloat(part.price_ic ?? part.price_ic_full) || 0;
    const pzn = parseFloat(part.price_pzn ?? part.price_sto) || 0;
    return Math.abs(ic - pzn) > 0.01;
};

// ===================================================================
// СМЕНА СТАТУСА С ПОДТВЕРЖДЕНИЕМ И ОЧИСТКОЙ ДАТЫ ПРИ «В ПОИСКЕ»
// ===================================================================
const activateStatusSelect = (partId) => {
    editingStatusId.value = partId;
    nextTick(() => {
        if (statusSelectRef.value) {
            const el = Array.isArray(statusSelectRef.value) ? statusSelectRef.value[0] : statusSelectRef.value;
            el?.focus();
        }
    });
};

const onStatusSelectChange = async (part, newStatus) => {
    editingStatusId.value = null;
    if (part.status === newStatus) return;

    const ok = confirm(`Изменить статус детали "${part.name}" на "${newStatus}"?`);
    if (!ok) return;

    const oldStatus = part.status;
    part.status = newStatus;

    if (newStatus === 'В поиске') {
        part.order_date = null;
    }

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/field`, {
            field: 'status',
            value: newStatus,
        });
        if (res.data?.data) {
            Object.assign(part, res.data.data);
        }
        emit('updated');
    } catch (err) {
        console.error('Ошибка статуса:', err);
        part.status = oldStatus;
    }
};

const getStatusClass = (status) => {
    const s = String(status || '').trim().toLowerCase();
    switch (s) {
        case 'заказана': return 'status-theme-ordered';
        case 'ожидается доставка': return 'status-theme-transit';
        case 'на складе': return 'status-theme-stock';
        case 'выдана на сто': return 'status-theme-issued';
        case 'установлена': return 'status-theme-installed';
        case 'отказ': return 'status-theme-rejected';
        case 'в поиске':
        default: return 'status-theme-search';
    }
};

// ===================================================================
// ССЫЛКИ НА АГРЕГАТОРЫ
// ===================================================================
const hasValidPartNumber = (part) => {
    if (!part?.part_number) return false;
    const cleaned = String(part.part_number).replace(/[^a-zA-Zа-яА-ЯёЁ0-9]/g, '').trim();
    return cleaned.length > 0;
};

const cyrToLatMap = {
    'А': 'A', 'В': 'B', 'Е': 'E', 'К': 'K', 'М': 'M', 'Н': 'H', 'О': 'O', 'Р': 'P', 'С': 'C', 'Т': 'T', 'У': 'Y', 'Х': 'X',
    'а': 'a', 'в': 'b', 'е': 'e', 'к': 'k', 'м': 'm', 'н': 'h', 'о': 'o', 'р': 'p', 'с': 'c', 'т': 't', 'у': 'y', 'х': 'x',
};

const normalizePartNumberForUrl = (num) => {
    if (!num) return '';
    const raw = String(num).replace(/[^a-zA-Zа-яА-ЯёЁ0-9]/g, '');
    return raw.split('').map(ch => cyrToLatMap[ch] || ch).join('');
};

const getEuroAutoUrl = (num) => `https://euroauto.ru/search/parts/${encodeURIComponent(normalizePartNumberForUrl(num))}/`;
const getAutopiterUrl = (num) => `https://autopiter.ru/goods/${encodeURIComponent(normalizePartNumberForUrl(num))}`;
const getEmexUrl = (num) => `https://emex.ru/products/${encodeURIComponent(normalizePartNumberForUrl(num))}`;

// Состояние модального окна % ПЗН (ОСАГО)
const showPznPercentModal = ref(false);
const pznPercentValue = ref('10');
const isApplyingPercent = ref(false);
const pznPercentInputRef = ref(null);

const isPznPercentValid = computed(() => {
    const val = parseFloat(String(pznPercentValue.value).replace(',', '.'));
    return !isNaN(val) && val > 0 && val <= 500;
});

const openPznPercentModal = () => {
    if (props.isStoUser) return;
    showPznPercentModal.value = true;
    nextTick(() => {
        pznPercentInputRef.value?.focus();
        pznPercentInputRef.value?.select();
    });
};

const closePznPercentModal = () => {
    showPznPercentModal.value = false;
};

const applyPznPercent = async () => {
    if (!isPznPercentValid.value || isApplyingPercent.value) return;

    const pct = parseFloat(String(pznPercentValue.value).replace(',', '.'));
    isApplyingPercent.value = true;

    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/spare-parts/bulk-pzn-percent`, {
            percent: pct,
        });

        if (res.data?.data) {
            parts.value = res.data.data;
        }
        closePznPercentModal();
        emit('updated');
    } catch (err) {
        console.error('Ошибка массового увеличения цен ПЗН:', err);
        alert('Не удалось обновить цены ПЗН');
    } finally {
        isApplyingPercent.value = false;
    }
};

// ===================================================================
// ЧАТ И КОММЕНТАРИИ
// ===================================================================
const openChatModal = (part) => {
    activeChatPart.value = part;
    newCommentText.value = '';
    nextTick(scrollChatToBottom);
};

const closeChatModal = () => {
    activeChatPart.value = null;
    newCommentText.value = '';
};

const currentPartComments = computed(() => activeChatPart.value?.comments || []);

const getLatestCommentText = (part) => {
    if (part.comments && part.comments.length > 0) {
        return part.comments[part.comments.length - 1].comment;
    }
    return part.comment || '';
};

const getCommentsCount = (part) => part.comments ? part.comments.length : (part.comment ? 1 : 0);
const isMyComment = () => false;

const scrollChatToBottom = () => {
    if (chatContainerRef.value) {
        chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight;
    }
};

const sendComment = async () => {
    const txt = newCommentText.value.trim();
    if (!txt || !activeChatPart.value || isSendingComment.value) return;

    isSendingComment.value = true;
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/spare-parts/${activeChatPart.value.id}/comments`, {
            comment: txt,
        });
        const newObj = res.data.data;
        if (!activeChatPart.value.comments) activeChatPart.value.comments = [];
        activeChatPart.value.comments.push(newObj);
        activeChatPart.value.comment = newObj.comment;
        newCommentText.value = '';
        nextTick(scrollChatToBottom);
        emit('updated');
    } catch (err) {
        console.error('Ошибка отправки комментария:', err);
    } finally {
        isSendingComment.value = false;
    }
};

// ===================================================================
// ПАРЯЩИЙ ТУЛТИП АВТОРА
// ===================================================================
const handleCreatorMouseEnter = (event, part) => {
    if (!isManualRow(part)) return;
    const rect = event.currentTarget.getBoundingClientRect();
    const authorName = part.creator
        ? [part.creator.last_name, part.creator.name, part.creator.patronymic].filter(Boolean).join(' ')
        : '—';
    const createdDate = part.created_at ? formatDateTime(part.created_at) : '—';

    creatorTooltip.author = authorName || 'Сотрудник';
    creatorTooltip.date = createdDate;
    creatorTooltip.x = rect.left + rect.width / 2;
    creatorTooltip.y = rect.top - 8;
    creatorTooltip.visible = true;
};

const handleCreatorMouseLeave = () => {
    creatorTooltip.visible = false;
};

// Обработчик клика/двойного клика
const handlePriceCellClick = (part, field, title, mrkType, event) => {
    if (event) {
        event.stopPropagation();
    }

    const now = Date.now();
    const gap = now - clickTapState.lastTime;

    // 1. ДВОЙНОЙ КЛИК / ДВОЙНОЙ ТАП (интервал от 40 до 320 мс по той же ячейке)
    if (
        gap < 320 &&
        gap > 40 &&
        clickTapState.lastPartId === part.id &&
        clickTapState.lastType === mrkType
    ) {
        if (clickTapState.timer) {
            clearTimeout(clickTapState.timer);
            clickTapState.timer = null;
        }
        clickTapState.lastTime = 0;
        clickTapState.lastPartId = null;
        clickTapState.lastType = null;

        closePriceCalc();
        if (mrkType) {
            toggleMrkSelection(part, mrkType);
        }
        return;
    }

    clickTapState.lastTime = now;
    clickTapState.lastPartId = part.id;
    clickTapState.lastType = mrkType;

    if (clickTapState.timer) {
        clearTimeout(clickTapState.timer);
    }

    const targetEl = event?.currentTarget || event?.target;

    clickTapState.timer = setTimeout(() => {
        openPriceCalc(part, field, title, targetEl);
        clickTapState.timer = null;
    }, 220);
};

// Мелкие детали (2%)
const handleSmallPartsClick = async () => {
    try {
        const checkRes = await axios.post(`/api/applications/${props.applicationId}/spare-parts/small-parts`);
        if (checkRes.data.exists) {
            if (confirm('Позиция "Мелкие детали" уже создана. Удалить её из сметы?')) {
                await axios.delete(`/api/applications/${props.applicationId}/spare-parts/${checkRes.data.part_id}`);
                await fetchParts();
                emit('updated');
            }
        } else {
            await fetchParts();
            emit('updated');
        }
    } catch (err) {
        console.error('Ошибка мелких деталей:', err);
    }
};

// Удаление
const confirmDelete = async (part) => {
    if (!confirm(`Удалить запчасть "${part.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/spare-parts/${part.id}`);
        parts.value = parts.value.filter((p) => p.id !== part.id);
        emit('updated');
    } catch (err) {
        console.error('Ошибка удаления:', err);
    }
};

const onGlobalClick = () => {
    editingStatusId.value = null;
    closePriceCalc();
};

// История изменений
const openHistoryModal = async () => {
    showHistoryModal.value = true;
    historyLoading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/spare-parts/history`);
        historyList.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка истории:', err);
    } finally {
        historyLoading.value = false;
    }
};

// Форматирование
const isManualRow = (part) => part.is_manual === true || part.is_manual === 1 || Boolean(part.created_by_user_id);

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatMoneyOrEmpty = (val) => {
    const n = parseFloat(val);
    if (!n || isNaN(n)) return '';
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDateForInput = (dateStr) => dateStr ? String(dateStr).substring(0, 10) : '';

const formatDateDisplay = (dateStr) => {
    if (!dateStr) return '—';
    const clean = String(dateStr).substring(0, 10);
    const p = clean.split('-');
    return p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : clean;
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const translateField = (field) => {
    const map = {
        name: 'Наименование',
        part_number: 'Кат. номер',
        price_ic: 'Цена СК',
        price_ic_full: 'Цена СК',
        price_ic_half: 'Цена со скидкой',
        price_pzn: 'Цена ПЗН',
        price_original: 'Оригинал',
        price_analogue: 'Аналог',
        price_used: 'Б/у',
        price_sto: 'Цена СТО',
        selected_mrk: 'Выбор МРК',
        status: 'Статус',
        order_date: 'Дата заказа',
        delivery_date: 'Дата поставки',
        comment: 'Комментарий',
        created: 'Создание',
        deleted: 'Удаление',
        supplier_selected: 'Выбор закупки',
    };
    return map[field] || field;
};
// Функция автоматического выделения всего текста ячейки при фокусе
const selectAllCellContent = (event) => {
    const el = event.currentTarget || event.target;
    if (!el) return;

    // Даем микро-задержку (через requestAnimationFrame или setTimeout), 
    // чтобы событие мыши (mouseup) не сбросило выделение сразу после фокуса
    requestAnimationFrame(() => {
        try {
            const range = document.createRange();
            range.selectNodeContents(el);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        } catch (e) {
            console.error('Ошибка выделения содержимого ячейки:', e);
        }
    });
};
onMounted(fetchParts);
</script>

<style scoped>
/* ======================================================== */
/* ЗАГОЛОВОК БЛОКА ЗАПЧАСТИ                                 */
/* ======================================================== */
.section-icon-box {
    width: 26px;
    height: 26px;
    background-color: #e0f2fe;
    color: #0284c7;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
}

.section-title {
    font-size: 0.92rem;
}

.fs-7 {
    font-size: 0.78rem;
}

.btn-upload-sm {
    font-size: 0.72rem;
    padding: 2px 9px;
    border-radius: 4px;
}

.btn-history-sm {
    font-size: 0.72rem;
    padding: 2px 9px;
    border-radius: 4px;
}

/* ======================================================== */
/* НАГЛЯДНЫЕ КОМПАКТНЫЕ ПЛАШКИ СУММ НАД ТАБЛИЦЕЙ            */
/* ======================================================== */
.sum-summary-bar {
    background-color: #f8fafc;
    padding: 4px 6px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.summary-pill {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.74rem;
    line-height: 1.2;
}

/* Плашка СК: синяя фирменная */
.pill-sk {
    background-color: #1e293b;
    color: #ffffff;
    border: 1px solid #0f172a;
}

.pill-sk .pill-label {
    color: #94a3b8;
    font-weight: 500;
}

/* Плашка Скидка СК (КАСКО) */
.pill-discount {
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    user-select: none;
}

.pill-discount .pill-label {
    color: #1e40af;
    font-weight: 500;
}

/* Плашка Цена со скидкой (КАСКО) */
.pill-casco-half {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.pill-casco-half .pill-label {
    color: #166534;
    font-weight: 500;
}

/* Плашка ПЗН (ОСАГО) */
.pill-pzn {
    background-color: #ffffff;
    color: #1e293b;
    border: 1px solid #cbd5e1;
}

.pill-pzn .pill-label {
    color: #64748b;
    font-weight: 500;
}

/* Если есть расхождение сумм */
.pill-pzn-diff {
    background-color: #fef08a !important;
    border-color: #fde047 !important;
    color: #854d0e !important;
}

.pill-pzn-diff .pill-label {
    color: #a16207 !important;
}

/* Мини-бейдж разницы */
.diff-badge {
    font-size: 0.70rem;
    padding: 2px 6px;
    border-radius: 3px;
}

.diff-up {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.diff-down {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* ======================================================== */
/* ТАБЛИЦА                                                  */
/* ======================================================== */
.table-mandarin {
    table-layout: fixed !important;
    width: 100% !important;
    min-width: 1050px !important;
    /* Гарантирует горизонтальный скролл на смартфонах и планшетах */
    font-size: 0.70rem !important;
    border-collapse: collapse;
    line-height: 1.15;
}

.header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-size: 0.69rem !important;
    font-weight: 700;
    padding: 3px 2px !important;
    border: 1px solid #1f8abf;
    white-space: nowrap;
}

/* Уменьшенные отступы в ячейках для максимальной плотности */
.table-mandarin td {
    padding: 1px 2px !important;
    height: 22px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.col-idx {
    width: 26px;
    min-width: 26px;
    max-width: 26px;
}

.col-name {
    width: auto;
    min-width: 150px;
}

.col-part-num {
    width: 88px;
    min-width: 78px;
    max-width: 100px;
}

.col-order-num {
    width: 88px;
    min-width: 78px;
    max-width: 100px;
}

.col-price {
    width: 68px;
    min-width: 62px;
    max-width: 74px;
}

.col-shop-link {
    width: 24px;
    min-width: 24px;
    max-width: 26px;
}

.col-comment {
    width: 100px;
    min-width: 90px;
    max-width: 120px;
}

.col-status {
    width: 92px;
    min-width: 85px;
    max-width: 100px;
}

.col-date {
    width: 78px;
    min-width: 74px;
    max-width: 82px;
}

.col-trash {
    width: 24px;
    min-width: 24px;
    max-width: 26px;
}

.row-auto td {
    background-color: #ffffff;
}

.row-manual td {
    background-color: #f0f9ff !important;
}

.row-small-parts td {
    background-color: #f8fafc !important;
}

/* Темные ячейки цен СК */
td.cell-dark {
    background-color: #1e293b !important;
    color: #ffffff !important;
}

/* Подсветка согласованной цены со скидкой (КАСКО) */
.casco-half-highlight {
    color: #86efac !important;
    font-weight: 700;
}

/* ЯРКО-ЖЕЛТОЕ ВЫДЕЛЕНИЕ ЦЕНЫ ПЗН ПРИ НЕСОВПАДЕНИИ С ЦЕНОЙ СК */
td.cell-pzn-diff-yellow {
    background-color: #fef08a !important;
    color: #000000 !important;
    font-weight: 900 !important;
    box-shadow: inset 0 0 0 1px #eab308;
}

.editable-cell {
    outline: none;
    cursor: text;
}

.editable-cell:focus {
    background-color: #e0f2fe !important;
    box-shadow: inset 0 0 0 1px #0284c7;
    color: #000000 !important;
}

/* Выбор варианта закупки по двойному клику */
.selectable-price {
    user-select: none;
    cursor: pointer;
    transition: background-color 0.12s ease;
    touch-action: manipulation;
    -webkit-touch-callout: none;
}

.selected-mrk-active {
    background: linear-gradient(135deg, #16a34a, #22c55e) !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 1px #15803d;
}

/* Ссылки на агрегаторы */
.cell-link {
    vertical-align: middle;
}

.shop-btn-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 16px;
    font-size: 0.65rem;
    font-weight: 800;
    text-decoration: none;
    border-radius: 2px;
    color: #ffffff;
    line-height: 1;
}

.ea-link {
    background-color: #0284c7;
}

.ea-link:hover {
    opacity: 0.85;
}

.ap-link {
    background-color: #f59e0b;
}

.ap-link:hover {
    opacity: 0.85;
}

.em-link {
    background-color: #dc2626;
}

.em-link:hover {
    opacity: 0.85;
}

/* Комментарий */
.comment-cell {
    cursor: pointer;
    background-color: rgba(241, 245, 249, 0.4);
}

.comment-cell:hover {
    background-color: #e2e8f0;
}

.comment-preview {
    font-size: 0.68rem;
    color: #0f172a;
}

.comment-placeholder {
    color: #94a3b8;
    font-size: 0.66rem;
    font-style: italic;
}

/* Статусы */
.cell-status-wrapper {
    cursor: pointer;
}

.status-theme-pill {
    display: inline-block;
    width: 96%;
    padding: 1px 3px;
    border-radius: 2px;
    font-size: 0.64rem;
    font-weight: 700;
    text-align: center;
    line-height: 15px;
    border: 1px solid transparent;
    user-select: none;
    text-transform: uppercase;
}

.status-theme-search {
    background-color: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

.status-theme-ordered {
    background-color: #e0f2fe;
    color: #0284c7;
    border-color: #bae6fd;
}

.status-theme-transit {
    background-color: #e0e7ff;
    color: #4338ca;
    border-color: #c7d2fe;
}

.status-theme-stock {
    background-color: #fef3c7;
    color: #b45309;
    border-color: #fde68a;
}

.status-theme-issued {
    background-color: #f3e8ff;
    color: #7e22ce;
    border-color: #e9d5ff;
}

.status-theme-installed {
    background-color: #dcfce7;
    color: #15803d;
    border-color: #bbf7d0;
}

.status-theme-rejected {
    background-color: #fee2e2;
    color: #b91c1c;
    border-color: #fecaca;
}

.form-select-micro {
    height: 19px !important;
    font-size: 0.66rem !important;
    padding: 0 2px !important;
    border-radius: 2px;
}

.cell-readonly-date {
    color: #475569;
    font-size: 0.68rem;
}

.date-input-micro {
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    font-size: 0.67rem;
    padding: 0 1px;
    height: 19px;
    width: 96%;
}

.badge-index {
    display: inline-block;
    min-width: 18px;
    padding: 0 2px;
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
}

.badge-manual {
    background-color: #bae6fd;
    color: #0369a1;
    border: 1px solid #7dd3fc;
    border-radius: 2px;
    cursor: help;
}

/* Бейджи КАСКО / ОСАГО */
.badge-type-pill {
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-weight: 700;
}

.badge-casco {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}

.badge-osago {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

/* ======================================================== */
/* КНОПКИ ДЕЙСТВИЙ ПОД ТАБЛИЦЕЙ                             */
/* ======================================================== */
.table-bottom-actions {
    padding-top: 6px;
}

.table-mandarin th {
    padding: 3px 1px !important;
    font-size: 0.68rem !important;
}

.btn-mandarin-add-sm {
    background-color: #299bd1;
    color: #ffffff;
    font-size: 0.72rem;
    padding: 3px 10px;
    border-radius: 4px;
    border: none;
}

.btn-mandarin-add-sm:hover {
    background-color: #0284c7;
}

.btn-small-parts-sm {
    background-color: #475569;
    color: #ffffff;
    font-size: 0.72rem;
    padding: 3px 10px;
    border-radius: 12px;
    border: none;
}

.btn-small-parts-sm:hover {
    background-color: #334155;
}

.btn-trash-micro {
    font-size: 0.74rem;
    line-height: 1;
}

.btn-trash-micro:hover {
    color: #dc2626 !important;
}

/* Чат */
.modal-dialog-chat {
    background: #ffffff;
    border-radius: 10px;
    width: 90%;
    max-width: 480px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    animation: modalScaleUp 0.15s ease-out;
}

.chat-messages-container {
    height: 340px;
    max-height: 340px;
    overflow-y: auto;
    background-color: #f8fafc;
}

.chat-bubble-wrapper {
    display: flex;
    justify-content: flex-start;
    width: 100%;
}

.chat-bubble-outgoing {
    justify-content: flex-end;
}

.chat-bubble {
    max-width: 82%;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px 8px 8px 1px;
    padding: 6px 10px;
}

.chat-bubble-outgoing .chat-bubble {
    background-color: #eff6ff;
    border-color: #bfdbfe;
    border-radius: 8px 8px 1px 8px;
}

.chat-author {
    font-size: 0.68rem;
    color: #1e293b;
}

.chat-time {
    font-size: 0.62rem;
}

.chat-text {
    font-size: 0.74rem;
    line-height: 1.35;
    color: #0f172a;
    word-break: break-word;
}

.chat-textarea {
    resize: none;
    font-size: 0.74rem;
}

/* Парящий тултип */
.floating-creator-tooltip {
    position: fixed !important;
    transform: translate(-50%, -100%) !important;
    background-color: #0f172a !important;
    color: #ffffff !important;
    border-radius: 5px !important;
    padding: 5px 9px !important;
    font-size: 0.70rem !important;
    white-space: nowrap !important;
    z-index: 9999999 !important;
    pointer-events: none !important;
    animation: tooltipFadeIn 0.12s ease-out;
}

.floating-creator-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #0f172a transparent transparent transparent;
}

.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1090;
    backdrop-filter: blur(2px);
}

.modal-dialog-custom {
    background: #ffffff;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    overflow: hidden;
}

@keyframes modalScaleUp {
    from {
        opacity: 0;
        transform: scale(0.95);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, -95%);
    }

    to {
        opacity: 1;
        transform: translate(-50%, -100%);
    }
}

/* Интерактивный заголовок колонки Цена ПЗН / скидки */
.col-price-pzn-header {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease;
}

.col-price-pzn-header:hover {
    background-color: #1f8abf !important;
    color: #fef08a !important;
}

.header-pct-icon {
    font-size: 0.65rem;
    background: rgba(255, 255, 255, 0.2);
    padding: 1px 3px;
    border-radius: 2px;
}

/* Модальное окно ввода процента */
.modal-dialog-percent {
    background: #ffffff;
    border-radius: 8px;
    width: 90%;
    max-width: 360px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
    animation: modalScaleUp 0.15s ease-out;
}

/* ======================================================== */
/* ОТСТУП И АККОРДЕОН                                       */
/* ======================================================== */
.spare-parts-module-container {
    width: 100%;
    max-width: 100%;
    margin-bottom: 2rem !important;
}

.accordion-toggle-btn {
    cursor: pointer;
    user-select: none;
    padding: 2px 4px;
    border-radius: 6px;
    transition: background-color 0.15s ease;
}

.accordion-toggle-btn:hover {
    background-color: #f1f5f9;
}

.accordion-arrow {
    display: inline-block;
    font-size: 0.82rem;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.accordion-arrow-rotated {
    transform: rotate(-90deg);
}

.cursor-pointer {
    cursor: pointer;
}

.select-none {
    user-select: none;
}

/* ======================================================== */
/* ДЕСКТОП: ТАБЛИЦА СТРОГО В ШИРИНУ ЭКРАНА (БЕЗ СКРОЛЛА)     */
/* ======================================================== */
.table-desktop-fit-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    background: #ffffff;
    /* Разрешаем скролл ВСЕГДА, когда контент шире контейнера */
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
}



@media (min-width: 1280px) {
    .col-name {
        width: 500px;
    }

    .table-desktop-fit-wrapper {
        overflow-x: auto;
    }
}

/* Ячейка даты поставки */
.cell-delivery-date {
    cursor: pointer;
    user-select: none;
    height: 22px;
    vertical-align: middle;
}

.cell-delivery-date:hover {
    background-color: #e0f2fe !important;
}

.delivery-date-display {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 0.69rem;
}

.delivery-date-display .date-text {
    color: #0f172a;
    font-weight: 500;
    letter-spacing: -0.2px;
}

.delivery-date-display .date-empty-placeholder {
    color: #94a3b8;
    font-size: 0.66rem;
    font-style: italic;
}

/* Прозрачный инпут поверх ячейки: клик в любое место ячейки открывает календарь */
.hidden-date-picker-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    border: none;
    padding: 0;
    margin: 0;
    z-index: 2;
}

/* Отключаем дефолтные стрелки и индикаторы календаря внутри браузеров WebKit */
.hidden-date-picker-input::-webkit-calendar-picker-indicator {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    opacity: 0;
    cursor: pointer;
}

/* 0. Автоматически из калькуляции — БЕЛЫЙ */
.row-auto-calc td {
    background-color: #ffffff !important;
}

/* 1. МРК дела — ПАСТЕЛЬНО-СИНИЙ / ГОЛУБОЙ */
.row-mrk td {
    background-color: #e0f2fe !important;
    /* Tailwind sky-100 */
}

.row-mrk:hover td {
    background-color: #bae6fd !important;
}

/* 2. Наставник дела — СИРЕНЕВЫЙ / ЛАВАНДОВЫЙ */
.row-mentor td {
    background-color: #f3e8ff !important;
    /* Tailwind purple-100 */
}

.row-mentor:hover td {
    background-color: #e9d5ff !important;
}

/* 3. Запчастист (СПМ) дела — СВЕТЛО-ЗЕЛЕНЫЙ */
.row-spm td {
    background-color: #dcfce7 !important;
    /* Tailwind green-100 */
}

.row-spm:hover td {
    background-color: #bbf7d0 !important;
}

/* 4. Сервис / СТО дела — ПАСТЕЛЬНО-ЖЕЛТЫЙ */
.row-sto td {
    background-color: #fef9c3 !important;
    /* Tailwind yellow-100 */
}

.row-sto:hover td {
    background-color: #fef08a !important;
}

/* 5. Другой пользователь — СВЕТЛО-КОРИЧНЕВЫЙ / ПЕСОЧНЫЙ */
.row-other td {
    background-color: #fed7aa !important;
    /* Мягкий бежево-коричневый / orange-200 */
}

.row-other:hover td {
    background-color: #fdba74 !important;
}

/* 6. ИИ анализ фото — ПАСТЕЛЬНО-РОЗОВЫЙ */
.row-ai td {
    background-color: #fce7f3 !important;
    /* Tailwind pink-100 */
}

.row-ai:hover td {
    background-color: #fbcfe8 !important;
}

/* ИСКЛЮЧЕНИЯ: Тёмные ячейки цен СК и ПЗН и выбранные варианты сохраняют свой стиль */
.spare-part-row td.cell-dark {
    background-color: #1e293b !important;
    color: #ffffff !important;
}

.spare-part-row td.selected-mrk-active {
    background: linear-gradient(135deg, #16a34a, #22c55e) !important;
    color: #ffffff !important;
}

/* ========================================================= */
/* ИНТЕРАКТИВНЫЙ HOVER ДЛЯ СТРОК СМЕТЫ                       */
/* ========================================================= */

.spare-part-row {
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

/* 0. Автоматически из калькуляции (Белый -> Мягкий нейтральный) */
.spare-part-row.row-auto-calc:hover,
.spare-part-row.row-auto-calc:hover>td {
    --bs-table-bg: #f8fafc !important;
    --bs-table-accent-bg: #f8fafc !important;
    background-color: #f7fbfe89 !important;
}

/* 1. МРК (Голубой -> Насыщенный небесный) */
.spare-part-row.row-mrk:hover,
.spare-part-row.row-mrk:hover>td {
    --bs-table-bg: #bae6fd !important;
    --bs-table-accent-bg: #bae6fd !important;
    background-color: #bae6fd !important;
}

/* 2. Наставник (Лавандовый -> Насыщенный сиреневый) */
.spare-part-row.row-mentor:hover,
.spare-part-row.row-mentor:hover>td {
    --bs-table-bg: #e9d5ff !important;
    --bs-table-accent-bg: #e9d5ff !important;
    background-color: #e9d5ff !important;
}

/* 3. Запчастист / СПМ (Светло-зеленый -> Мятно-зеленый) */
.spare-part-row.row-spm:hover,
.spare-part-row.row-spm:hover>td {
    --bs-table-bg: #bbf7d0 !important;
    --bs-table-accent-bg: #bbf7d0 !important;
    background-color: #bbf7d0 !important;
}

/* 4. Сервис / СТО (Пастельно-желтый -> Теплый золотистый) */
.spare-part-row.row-sto:hover,
.spare-part-row.row-sto:hover>td {
    --bs-table-bg: #fef08a !important;
    --bs-table-accent-bg: #fef08a !important;
    background-color: #fef08a !important;
}

/* 5. Другой пользователь (Песочный -> Насыщенный абрикосовый) */
.spare-part-row.row-other:hover,
.spare-part-row.row-other:hover>td {
    --bs-table-bg: #fdba74 !important;
    --bs-table-accent-bg: #fdba74 !important;
    background-color: #fdba74 !important;
}

/* 6. ИИ анализ (Розовый -> Насыщенный пудровый) */
.spare-part-row.row-ai:hover,
.spare-part-row.row-ai:hover>td {
    --bs-table-bg: #fbcfe8 !important;
    --bs-table-accent-bg: #fbcfe8 !important;
    background-color: #fbcfe8 !important;
}

/* Сохраняем темные ячейки цен СК и ПЗН даже при наведении на строку */
.spare-part-row:hover td.cell-dark-bg,
.spare-part-row:hover td.cell-dark {
    --bs-table-bg: #0f172a !important;
    --bs-table-accent-bg: #0f172a !important;
    background-color: #0f172a !important;
    color: #ffffff !important;
}

/* Ячейка и заголовок наименования */
.col-name,
th.col-name,
td.col-name {
    /* Задаем комфортную минимальную ширину */
    width: 240px !important;
    min-width: 200px !important;
    text-align: left !important;

    /* Разрешаем перенос строк и отключаем обрезание троеточием */
    white-space: normal !important;
    word-break: break-word !important;
    overflow: visible !important;
    text-overflow: clip !important;
    line-height: 1.25 !important;
}

/* Если внутри td есть contenteditable div или span */
td.col-name .cell-editable-text,
td.col-name [contenteditable] {
    white-space: normal !important;
    word-break: break-word !important;
    overflow: visible !important;
    text-overflow: clip !important;
    display: block !important;
    min-width: 190px !important;
    line-height: 1.25 !important;
}

/* Контейнер-обёртка таблицы */
.table-responsive,
.table-desktop-fit-wrapper,
.spare-parts-table-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    display: block !important;
}

/* Сама таблица */
.mandarin-table,
.spare-parts-table {
    /* На мобильных экран не должен насильно ужимать таблицу */
    min-width: 1100px !important;
    width: max-content !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}

/* Но на десктопе (экраны шире 1280px) таблица растягивается на 100% экрана */
@media (min-width: 1280px) {

    .mandarin-table,
    .spare-parts-table {
        width: 100% !important;
        min-width: 100% !important;
    }
}

.icon-parts {
    background-color: #f97316;
    color: #ffffff;
}
</style>