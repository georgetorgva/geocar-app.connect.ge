<?php

namespace App\Models\Translations;

//use App\Exceptions\NotFoundException;
use App\Models\Languages\Words;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use App\Exceptions;

class WordsRepository
{


    public function search(?string $keyword = '', ?int $page = 1, ?int $limit = 5)
    {
        $words = Words::where('key', 'LIKE', '%' . $keyword . '%')
            ->where('value', 'LIKE', '%' . $keyword . '%')
            ->orderBy('id', 'DESC')->paginate($limit);
            return $words;
    }

    public function getByKey(string $key)
    {
        $word = Words::where('key', $key)->first();
        if ($word instanceof Words) {
            return $word;
        } else {
            return $key;
        }
    }

    public function create(array $data)
    {
        $word = new Words([
            'key' => $data['key']
        ]);

        foreach (getLocales() as $locale) {
            $word->setTranslation('value', $locale->locale, $data['value'][$locale->locale]);
        }
        $word->save();
        return $word;
    }

    public function update(string $key, array $data)
    {
        $word = Words::where('key', $key)->first(); //
        foreach (getLocales() as $locale) {
            if (!empty($data['value_'.$locale->locale])) {
                $word->saveTranslation($locale->locale, [
                    'value'        => $data['value_'.$locale->locale],
                    'locale' => $locale->locale
                ]);
            } else {
                $word->translations()->where('locale', $locale->locale)->delete();
            }
        }
        return $word;
    }

    public function delete(string $key)
    {
        $word = Words::where('key', $key)->delete();
        return $word;
    }

    public function getAllTranslation(string $key)
    {
        $word = Words::where('key', $key)->with('translations')->first();
        return $word;
    }

    public function all()
    {
        return Words::get();
    }
}
