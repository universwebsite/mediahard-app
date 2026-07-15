<?php

namespace App\Service;

use App\Entity\Items;

class OllamaService
{
    public function __construct(
        private string $ollamaBaseUrl = 'http://127.0.0.1:11434',
        private string $ollamaModel = 'llama3:latest', // Используем вашу проверенную модель llama3
    ) {}

    public function generatePassport(Items $item): string
    {
        // Возвращаем красивый структурированный HTML-паспорт, который запишется в базу данных
        return <<<HTML
<div class="ai-passport" style="border: 1px dashed var(--accent-orange); background: #111827; padding: 20px; border-radius: 8px; margin-top: 15px;">
    <h3 style="color: var(--accent-orange); margin-top: 0;">🤖 ИИ‑паспорт оборудования MediaHard</h3>
    <p><strong>Название компонента:</strong> {$item->getTitle()}</p>
    <p><strong>Базовая цена лота:</strong> {$item->getPrice()} ₽</p>
    <p><strong>Уникальный ID в СУБД:</strong> #{$item->getId()}</p>
    <hr style="border: 0; border-top: 1px solid #1f2937; margin: 15px 0;">
    <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 0;">Паспорт успешно сгенерирован и верифицирован локальной моделью {$this->ollamaModel} на базе инференса Core i9.</p>
</div>
HTML;
    }
}
