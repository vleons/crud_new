<?php
namespace SilexApp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DatabaseManager
{
    private $connection;
    private $uploadDir;
    private $publicDir;

    public function __construct(
        Connection $connection,
        string $uploadDir,
        string $publicDir
    ) {
        $this->connection = $connection;
        $this->uploadDir = $uploadDir;
        $this->publicDir = $publicDir;
        
        // Создаем папку для загрузок, если ее нет
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    private function handleFileUpload(?UploadedFile $file): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        $filename = uniqid().'.'.$file->guessExtension();
        $file->move($this->uploadDir, $filename);
        
        return $this->publicDir.'/'.$filename;
    }

    public function createProduct(array $data, ?UploadedFile $image): int
    {
        $imagePath = $this->handleFileUpload($image);
        
        $data['image_path'] = $imagePath;
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->connection->insert('products', $data);
        return (int)$this->connection->lastInsertId();
    }

    public function updateProduct(int $id, array $data, ?UploadedFile $image = null): bool
    {
        if ($image) {
            // Удаляем старое изображение, если есть
            $oldProduct = $this->getProductById($id);
            if ($oldProduct && $oldProduct['image_path']) {
                $this->deleteImage($oldProduct['image_path']);
            }
            
            $data['image_path'] = $this->handleFileUpload($image);
        }
        
        return $this->connection->update('products', $data, ['id' => $id]) > 0;
    }

    public function getAllProducts(): array
    {
        return $this->connection->fetchAll('
            SELECT * FROM products 
            ORDER BY created_at DESC
        ');
    }

    public function getProductById(int $id): ?array
    {
        $product = $this->connection->fetchAssoc('
            SELECT * FROM products 
            WHERE id = ?
        ', [$id]);
        
        return $product ?: null;
    }

    public function deleteProduct(int $id): bool
    {
        // Удаляем связанное изображение
        $product = $this->getProductById($id);
        if ($product && $product['image_path']) {
            $this->deleteImage($product['image_path']);
        }
        
        return $this->connection->delete('products', ['id' => $id]) > 0;
    }

    private function deleteImage(string $imagePath): void
    {
        $filename = basename($imagePath);
        $filePath = $this->uploadDir.'/'.$filename;
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}