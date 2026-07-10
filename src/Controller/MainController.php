<?php

namespace App\Controller;

use App\Repository\ItemsRepository;
use App\Service\OllamaService; 
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(ItemsRepository $itemsRepository): Response
    {
        $activeItems = $itemsRepository->findBy([], ['id' => 'DESC'], 3);

        return $this->render('main/index.html.twig', [
            'items' => $activeItems,
        ]);
    }

    #[Route('/item/{id}', name: 'app_item_show', methods: ['GET'])]
    public function show(int $id, ItemsRepository $itemsRepository, OllamaService $ollamaService, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $item = $itemsRepository->find($id);

        if (!$item) {
            throw $this->createNotFoundException('Компонент не найден.');
        }

        // Проверяем, генерировали ли мы паспорт ранее (ищем маркер "🤖" в описании)
        $hasAiPassport = str_contains($item->getDescription() ?? '', '🤖 ЦИФРОВОЙ ПАСПОРТ');

        if (!$hasAiPassport) {
            // Запускаем инференс на вашем Core i9
            $aiReport = $ollamaService->generatePassport($item->getTitle(), $item->getDescription());
            
            // Если ИИ успешно вернул текст (не null) — сохраняем в PostgreSQL 15 навсегда
            if ($aiReport !== null) {
                $updatedDescription = $item->getDescription() . "\n\n🤖 ЦИФРОВОЙ ПАСПОРТ OLLAMA AI:\n" . $aiReport;
                $item->setDescription($updatedDescription);
                $em->flush();
            } else {
                // Если Ollama выдала null (офлайн) — активируем Graceful Degradation и пишем алерт
                $this->addFlash('ai_error', 'ИИ-модуль верификации временно недоступен. Выведены базовые ТТХ.');
            }
        }

        return $this->render('main/show.html.twig', [
            'item' => $item,
        ]);
    }

}
