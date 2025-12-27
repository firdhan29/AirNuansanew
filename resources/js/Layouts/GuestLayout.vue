<script setup>
import { Link } from '@inertiajs/vue3';
import { Waves } from 'lucide-vue-next'; // Kita pakai ikon Waves lagi biar konsisten
</script>



<template>
    
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 relative overflow-hidden">
        
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-cyan-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        
        <div class="z-10 w-full sm:max-w-md mt-6 px-8 py-10 bg-white/80 backdrop-blur-xl shadow-2xl overflow-hidden sm:rounded-3xl border border-white/50 relative">
            
            <div class="flex flex-col items-center justify-center mb-8">
                <Link href="/" class="flex flex-col items-center gap-3 group">
                    <div class="relative flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-600 to-cyan-500 text-white shadow-lg shadow-blue-500/30 transition-transform group-hover:scale-110">
                        <Waves :size="40" class="fill-current" />
                    </div>
                    <div class="text-center">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-800">
                            Lapor<span class="text-blue-600">Air</span>
                        </h1>
                        <p class="text-xs font-bold tracking-wider text-slate-400 uppercase">Admin Portal</p>
                    </div>
                </Link>
            </div>

            <div class="mt-4">
                <slot />
            </div>

        </div>

        <div class="mt-8 text-center text-xs text-slate-400 z-10">
            &copy; 2025 Nuansa Manisi Regency. All rights reserved.
        </div>
    </div>
</template>

<style scoped>
/* Animasi lambat untuk background */
.animate-blob {
    animation: blob 7s infinite;
}
.animation-delay-2000 {
    animation-delay: 2s;
}
@keyframes blob {
    0% { transform: translate(0px, 0px) scale(1); }
    33% { transform: translate(30px, -50px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
    100% { transform: translate(0px, 0px) scale(1); }
}
</style>