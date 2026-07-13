<script setup>
const colors = ['#f59e0b', '#fbbf24', '#f472b6', '#a78bfa', '#34d399', '#60a5fa'];

const pieceStyle = (index) => {
    const left = ((index * 17) % 90) + 5;
    const delay = (index % 6) * 0.12;
    const duration = 2.4 + (index % 4) * 0.35;
    const size = 4 + (index % 3);

    return {
        left: `${left}%`,
        width: `${size}px`,
        height: `${size * 1.4}px`,
        backgroundColor: colors[index % colors.length],
        animationDelay: `${delay}s`,
        animationDuration: `${duration}s`,
    };
};
</script>

<template>
    <div class="birthday-confetti pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true">
        <span
            v-for="n in 12"
            :key="n"
            class="birthday-confetti__piece"
            :style="pieceStyle(n)"
        />
    </div>
</template>

<style>
.birthday-confetti__piece {
    position: absolute;
    top: -8%;
    border-radius: 1px;
    opacity: 0;
    animation-name: birthday-confetti-fall;
    animation-timing-function: ease-out;
    animation-iteration-count: 2;
    animation-fill-mode: forwards;
}

@keyframes birthday-confetti-fall {
    0% {
        opacity: 0;
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
    12% {
        opacity: 0.85;
    }
    100% {
        opacity: 0;
        transform: translate3d(0, 110%, 0) rotate(280deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .birthday-confetti__piece {
        animation: none;
        display: none;
    }
}
</style>
