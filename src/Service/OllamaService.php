<?php

namespace App\Service;

use App\Entity\Items;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;

class OllamaService
{
    public function __construct(
        private string $ollamaBaseUrl = 'http://127.0.0.1:11434',
        private string $ollamaModel = 'llama3.2',
        private HttpClientInterface $httpClient,
    ) {}

    public function generatePassport(Items $item): string
    {
        $itemTitle = $item->getTitle();
        $itemDescription = $item->getDescription() ?? '';

        // 1. Формируем промпт
        $prompt = <<<PROMPT
Ты — изолированный HTML-модуль верстки платформы MediaHard Enterprise.
Твоя единственная задача — взять исходный текст технических характеристик товара, 
очистить его от мусора и оформить в виде красивой, ровной таблицы.

КРИТИЧЕСКИЕ ПРАВИЛА:
1. Используй только те данные, названия и цифры, которые есть в тексте ниже.
2. КАТЕГОРИЧЕСКИ ЗАПРЕЩЕНО придумывать, дописывать или прогнозировать любые характеристики, которых нет в исходных данных.
3. Верни ответ СТРОГО в виде валидной HTML-таблицы (тег <table>) с классами для стилизации. Никакого лишнего текста до и после таблицы.

НАЗВАНИЕ ТОВАРА: {$itemTitle}
ИСХОДНЫЕ ТТХ: {$itemDescription}
PROMPT;

        try {
            // 2. Отправляем запрос к Ollama
            $response = $this->httpClient->request('POST', "{$this->ollamaBaseUrl}/api/generate", [
                'json' => [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => 900, // 15 минут для локальной генерации
            ]);

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \RuntimeException('Ollama вернул ошибку: HTTP ' . $response->getStatusCode());
            }

            $data = $response->toArray(false);
            $aiTable = ($data['response'] ?? '') . '';

            // ВАЖНО: проверяем, что ИИ действительно вернул таблицу
            if (strpos(trim($aiTable), '<table') !== 0) {
                // Если нет — делаем безопасный фоллбэк из исходных данных
                $aiTable = $this->renderFallbackSpecsTable($itemDescription);
            }

            // 3. Оборачиваем ответ в общий блок паспорта
            return <<<HTML
<div class="ai-passport" style="border: 1px dashed #f97316; background: #111827; padding: 20px; border-radius: 8px; margin-top: 15px; color: #f3f4f6;">
    <h3 style="color: #f97316; margin-top: 0; font-size: 1.2rem;">🤖 ИИ‑паспорт оборудования MediaHard</h3>
    <p><strong>Название компонента:</strong> {$item->getTitle()}</p>
    <p><strong>Базовая цена лота:</strong> {$item->getPrice()} ₽</p>
    <p><strong>Уникальный ID в СУБД:</strong> #{$item->getId()}</p>
    <hr style="border: 0; border-top: 1px solid #374151; margin: 15px 0;">
    <div style="line-height: 1.5;">
        {$aiTable}
    </div>
    <p style="color: #9ca3af; font-size: 0.85em; margin-top: 12px; margin-bottom: 0;">Паспорт сгенерирован моделью "{$this->ollamaModel}" на локальной инфраструктуре MediaHard.</p>
</div>
HTML;
        } catch (\Exception $e) {
            // Если Ollama недоступен — показываем фоллбэк и сообщение об ошибке
            $fallbackTable = $this->renderFallbackSpecsTable($itemDescription);

            return <<<HTML
<div class="ai-passport ai-passport-error" style="border: 1px dashed #ef4444; background: #1e1b24; padding: 20px; border-radius: 8px; margin-top: 15px; color: #fca5a5;">
    <h3 style="color: #ef4444; margin-top: 0;">⚠️ ИИ‑модуль временно недоступен</h3>
    <p>Не удалось сгенерировать паспорт: {$e->getMessage()}</p>
    <p style="font-size: 0.9em; color: #9ca3af;">Выведены базовые ТТХ. ИИ‑паспорт будет сформирован при восстановлении связи.</p>
    <div style="margin-top: 10px;">
        {$fallbackTable}
    </div>
</div>
HTML;
        }
    }

    private function renderFallbackSpecsTable(string $rawSpecs): string
    {
        // Простой парсинг «строка: значение» или просто вывод как есть в таблице 2 колонки
        $lines = array_filter(array_map('trim', explode("\n", $rawSpecs)));
        $rows = '';

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
            } else {
                $key = 'Характеристика';
                $value = $line;
            }
            $rows .= "<tr><th style='text-align: left; border-bottom: 1px solid #374151;'>{$key}</th><td style='border-bottom: 1px solid #374151; padding: 6px 0;'>{$value}</td></tr>";
        }

        if (empty($rows)) {
            $rows = '<tr><td colspan="2">Нет технических характеристик</td></tr>';
        }

        return <<<HTML
<table style="width: 100%; border-collapse: collapse; color: #f3f4f6; background: #111827;">
    <tbody>
        {$rows}
    </tbody>
</table>
HTML;
    }
}
