<template>
    <div class="legal-entities-book bg-white border rounded p-3 shadow-xs">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-building-fill text-primary me-2"></i>Наши Юридические Лица
                </h6>
                <small class="text-muted">Реквизиты собственных компаний (ООО, ИП), банковские счета и
                    подписанты</small>
            </div>
            <button class="btn btn-sm btn-primary fw-bold" @click="fetchItems">
                <i class="bi bi-arrow-clockwise me-1"></i> Обновить
            </button>
        </div>

        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span class="ms-2 small text-muted">Загрузка юр. лиц...</span>
        </div>

        <div v-else class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">#</th>
                        <th>Наименование</th>
                        <th>ИНН / КПП</th>
                        <th>ОГРН</th>
                        <th>ФИО Руководителя</th>
                        <th>Банк / Расчетный счет</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in items" :key="item.id">
                        <td class="text-center text-muted">{{ idx + 1 }}</td>
                        <td class="fw-semibold text-dark">{{ item.name }}</td>
                        <td class="font-monospace">{{ item.inn || '—' }} <span v-if="item.kpp">/ {{ item.kpp }}</span>
                        </td>
                        <td class="font-monospace">{{ item.ogrn || '—' }}</td>
                        <td>{{ item.director_name || '—' }}</td>
                        <td>
                            <div class="small fw-semibold">{{ item.bank_name || '—' }}</div>
                            <div class="font-monospace text-muted" style="font-size: 0.75rem;">{{ item.checking_account
                                || '' }}</div>
                        </td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td colspan="6" class="text-center py-4 text-muted">Юридические лица не найдены</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const items = ref([]);
const loading = ref(false);

const fetchItems = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/legal-entities');
        items.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки юр. лиц:', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchItems();
});
</script>

<style scoped>
.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>