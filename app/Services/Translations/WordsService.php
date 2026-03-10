<?php

namespace App\Services\Translations;

use App\Domain\Exceptions\InvalidPermissionException;
use App\Repositories\Translations\WordsCacheRepository;
use App\Repositories\Translations\WordsRepositoryInterface;
use App\Services\MainService;

class WordsService extends MainService
{
    protected $wordsRepository;

    public function __construct(WordsRepositoryInterface $wordsRepository)
    {
        $this->wordsRepository = $wordsRepository;
    }


    public function search(?string $keyword = '', ?int $page = 1, ?int $limit = 5)
    {
        $words =  $this->wordsRepository->search($keyword, $page, $limit);
        return $words;
    }

    public function getByKey(?string $key)
    {
        return $this->wordsRepository->getByKey($key);
    }

    public function create($data)
    {
        return $this->wordsRepository->create($data);
    }

    public function update($key, $data)
    {
        return $this->wordsRepository->update($key, $data);
    }

    public function delete($key)
    {
        return $this->wordsRepository->delete($key);
    }

    public function getAllTranslation(string $key)
    {
        return $this->wordsRepository->getAllTranslation($key);
    }

    public function all()
    {
        return $this->wordsRepository->all();
    }
}
