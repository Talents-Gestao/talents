<script setup>
defineProps({
    colspan: { type: [Number, String], required: true },
    message: { type: String, required: true },
});
</script>

<template>
    <tr>
        <td :colspan="colspan" class="px-4 py-10 text-center text-sm text-slate-500">
            {{ message }}
        </td>
    </tr>
</template>
