const API_URL = 'api/analyze.php';
const STORAGE_KEY = 'deepseek_api_key';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('analyzeForm');
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    const results = document.getElementById('results');
    const aiSection = document.getElementById('aiSection');
    const settingsPanel = document.getElementById('settingsPanel');
    const toggleSettings = document.getElementById('toggleSettings');
    const apiKeyInput = document.getElementById('apiKey');
    const saveApiKeyBtn = document.getElementById('saveApiKey');
    const toggleKeyVisibility = document.getElementById('toggleKeyVisibility');
    const keyStatus = document.getElementById('keyStatus');
    const useAICheckbox = document.getElementById('useAI');

    function loadApiKey() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            apiKeyInput.value = saved;
            useAICheckbox.checked = true;
        } else {
            useAICheckbox.checked = false;
        }
    }

    function showKeyStatus(message, colorClass) {
        keyStatus.classList.remove('hidden');
        keyStatus.innerHTML = `<i class="fas fa-check-circle mr-1"></i><span class="${colorClass}">${message}</span>`;
        setTimeout(() => {
            keyStatus.classList.add('hidden');
        }, 3000);
    }

    toggleSettings.addEventListener('click', (e) => {
        e.preventDefault();
        settingsPanel.classList.toggle('hidden');
    });

    toggleKeyVisibility.addEventListener('click', () => {
        const isPassword = apiKeyInput.type === 'password';
        apiKeyInput.type = isPassword ? 'text' : 'password';
        toggleKeyVisibility.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
    });

    saveApiKeyBtn.addEventListener('click', () => {
        const key = apiKeyInput.value.trim();
        
        if (!key) {
            localStorage.removeItem(STORAGE_KEY);
            showKeyStatus('API Key eliminada', 'text-yellow-600');
            useAICheckbox.checked = false;
            return;
        }
        
        localStorage.setItem(STORAGE_KEY, key);
        showKeyStatus('API Key guardada correctamente', 'text-green-600');
        useAICheckbox.checked = true;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const url = document.getElementById('url').value.trim();
        const useAI = useAICheckbox.checked;
        const apiKey = apiKeyInput.value.trim();
        
        if (!url) return;
        
        await analyzeSEO(url, useAI, apiKey);
    });

    loadApiKey();

    async function analyzeSEO(url, useAI, apiKey) {
        showLoading();
        hideError();
        hideResults();
        
        try {
            updateLoadingStatus('Conectando con el sitio web...');
            
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url, use_ai: useAI, api_key: apiKey }),
            });
            
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Error al analizar el sitio');
            }
            
            updateLoadingStatus('Procesando resultados...');
            
            displayResults(data.seo_report);
            
            if (data.ai_analysis && !data.ai_analysis.error) {
                displayAIAnalysis(data.ai_analysis);
            } else if (data.ai_analysis?.error) {
                showAIError(data.ai_analysis.error);
            }
            
            hideLoading();
            showResults();
            
        } catch (err) {
            hideLoading();
            showError(err.message);
        }
    }

    function showLoading() { loading.classList.remove('hidden'); }
    function hideLoading() { loading.classList.add('hidden'); }
    function updateLoadingStatus(message) { document.getElementById('loadingStatus').textContent = message; }
    function showError(message) {
        error.classList.remove('hidden');
        document.getElementById('errorMessage').textContent = message;
    }
    function hideError() { error.classList.add('hidden'); }
    function showResults() { results.classList.remove('hidden'); }
    function hideResults() {
        results.classList.add('hidden');
        aiSection.classList.add('hidden');
    }

    function displayResults(report) {
        displayScore(report.score);
        displaySummary(report.summary);
        displayMetaTags(report.meta_tags);
        displayHeadings(report.headings);
        displayImages(report.images);
        displayLinks(report.links);
        displayIssues(report.issues);
        document.getElementById('analyzedUrl').textContent = `URL analizada: ${report.url}`;
    }

    function displayScore(score) {
        const circle = document.getElementById('scoreCircle');
        const value = document.getElementById('scoreValue');
        const text = document.getElementById('scoreText');
        
        const circumference = 2 * Math.PI * 45;
        const offset = circumference - (score / 100) * circumference;
        
        let color = '#ef4444';
        let scoreText = 'Necesita mejoras urgentes';
        
        if (score >= 80) { color = '#22c55e'; scoreText = '¡Excelente!'; }
        else if (score >= 60) { color = '#eab308'; scoreText = 'Buen trabajo'; }
        else if (score >= 40) { color = '#f97316'; scoreText = 'Requiere atención'; }
        
        circle.style.stroke = color;
        circle.style.strokeDashoffset = offset;
        value.textContent = score;
        text.textContent = scoreText;
    }

    function displaySummary(summary) {
        document.getElementById('criticalCount').textContent = summary.critical;
        document.getElementById('warningCount').textContent = summary.warnings;
        document.getElementById('infoCount').textContent = summary.info;
    }

    function displayMetaTags(meta) {
        const container = document.getElementById('metaTagsContent');
        const items = [
            { label: 'Título', value: meta.title || 'No encontrado', valid: meta.title?.length >= 30 && meta.title?.length <= 60 },
            { label: 'Descripción', value: meta.description || 'No encontrada', valid: meta.description?.length >= 70 && meta.description?.length <= 160 },
            { label: 'Palabras clave', value: meta.keywords || 'No definidas', valid: !!meta.keywords },
            { label: 'Canonical', value: meta.canonical || 'No definida', valid: !!meta.canonical },
            { label: 'Viewport', value: meta.viewport || 'No configurado', valid: !!meta.viewport },
            { label: 'Idioma', value: meta.lang || 'No definido', valid: !!meta.lang },
            { label: 'Charset', value: meta.charset || 'No definido', valid: !!meta.charset },
        ];
        
        container.innerHTML = items.map(item => `
            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <i class="fas ${item.valid ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500'} mt-1"></i>
                <div class="flex-1">
                    <div class="text-xs font-semibold text-gray-500 uppercase">${item.label}</div>
                    <div class="text-sm text-gray-800 mt-1 truncate">${item.value}</div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full ${item.valid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${item.valid ? 'OK' : 'Falta'}</span>
            </div>
        `).join('');
    }

    function displayHeadings(headings) {
        const container = document.getElementById('headingsContent');
        let html = '';
        
        for (let i = 1; i <= 6; i++) {
            const items = headings[`h${i}`] || [];
            if (items.length > 0) {
                html += `<div class="mb-3"><div class="text-xs font-semibold text-gray-500 uppercase mb-2">H${i} (${items.length})</div>${items.map(item => `<div class="text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded" style="padding-left:${i * 12}px">${item}</div>`).join('')}</div>`;
            }
        }
        
        container.innerHTML = html || '<p class="text-gray-500 text-sm">No se encontraron encabezados</p>';
    }

    function displayImages(images) {
        const container = document.getElementById('imagesContent');
        const withAlt = images.total - images.without_alt;
        
        container.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg"><div class="text-2xl font-bold text-green-600">${withAlt}</div><div class="text-sm text-green-700">Con alt</div></div>
                <div class="text-center p-4 bg-red-50 rounded-lg"><div class="text-2xl font-bold text-red-600">${images.without_alt}</div><div class="text-sm text-red-700">Sin alt</div></div>
            </div>
            <div class="mt-4 text-sm text-gray-600">Total: <strong>${images.total}</strong></div>
        `;
    }

    function displayLinks(links) {
        const container = document.getElementById('linksContent');
        
        container.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg"><div class="text-2xl font-bold text-blue-600">${links.internal}</div><div class="text-sm text-blue-700">Internos</div></div>
                <div class="text-center p-4 bg-purple-50 rounded-lg"><div class="text-2xl font-bold text-purple-600">${links.external}</div><div class="text-sm text-purple-700">Externos</div></div>
            </div>
            <div class="mt-4 text-sm text-gray-600">Total: ${links.total} | NoFollow: ${links.nofollow} | Vacíos: ${links.empty}</div>
        `;
    }

    function displayIssues(issues) {
        const container = document.getElementById('issuesContent');
        
        if (issues.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i><p class="text-gray-600">¡No se encontraron problemas!</p></div>';
            return;
        }
        
        const config = { critical: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700' }, warning: { bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700' }, info: { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' } };
        
        container.innerHTML = issues.map(issue => {
            const c = config[issue.type] || config.info;
            return `<div class="${c.bg} ${c.border} border rounded-lg p-4 mb-3"><div class="flex items-start gap-3"><i class="fas ${issue.type === 'critical' ? 'fa-times-circle' : issue.type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} ${c.text} mt-1"></i><div class="flex-1"><span class="text-xs font-semibold px-2 py-1 rounded-full ${c.bg} ${c.text} uppercase">${issue.type}</span><p class="text-gray-800 font-medium mt-2">${issue.message}</p><p class="text-sm text-gray-600 mt-1">💡 ${issue.suggestion}</p></div></div></div>`;
        }).join('');
    }

    function displayAIAnalysis(analysis) {
        aiSection.classList.remove('hidden');
        const container = document.getElementById('aiContent');
        let html = '';
        
        if (analysis.resumen) html += `<div class="bg-white rounded-lg p-4 mb-4"><h4 class="font-bold text-gray-800 mb-2">📋 Resumen Ejecutivo</h4><p class="text-gray-700">${analysis.resumen}</p></div>`;
        if (analysis.fortalezas?.length) html += `<div class="bg-white rounded-lg p-4 mb-4"><h4 class="font-bold text-gray-800 mb-2">✅ Fortalezas</h4><ul class="space-y-1">${analysis.fortalezas.map(f => `<li class="text-gray-700">✓ ${f}</li>`).join('')}</ul></div>`;
        if (analysis.problemas_criticos?.length) html += `<div class="bg-white rounded-lg p-4 mb-4"><h4 class="font-bold text-gray-800 mb-2">❌ Problemas Críticos</h4><ul class="space-y-1">${analysis.problemas_criticos.map(p => `<li class="text-gray-700">• ${p}</li>`).join('')}</ul></div>`;
        if (analysis.mejoras_sugeridas?.length) html += `<div class="bg-white rounded-lg p-4 mb-4"><h4 class="font-bold text-gray-800 mb-2">📈 Mejoras Sugeridas</h4><ol class="space-y-1">${analysis.mejoras_sugeridas.map((m, i) => `<li class="text-gray-700"><span class="font-bold">${i+1}.</span> ${m}</li>`).join('')}</ol></div>`;
        if (analysis.acciones_prioritarias?.length) html += `<div class="bg-gradient-to-r from-primary-500 to-blue-600 text-white rounded-lg p-4 mb-4"><h4 class="font-bold mb-2">🎯 Acciones Prioritarias</h4><ol class="space-y-1">${analysis.acciones_prioritarias.map((a, i) => `<li class="opacity-90"><span class="font-bold">${i+1}.</span> ${a}</li>`).join('')}</ol></div>`;
        
        container.innerHTML = html;
    }

    function showAIError(message) {
        aiSection.classList.remove('hidden');
        document.getElementById('aiContent').innerHTML = `<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"><div class="flex items-start gap-3"><i class="fas fa-exclamation-triangle text-yellow-500 mt-1"></i><div><h4 class="font-semibold text-yellow-800">Análisis IA no disponible</h4><p class="text-sm text-yellow-700 mt-1">${message}</p></div></div></div>`;
    }
});