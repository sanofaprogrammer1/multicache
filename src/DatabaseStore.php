<?php

namespace PulkitJalan\Cache;

use Illuminate\Cache\DatabaseStore as IlluminateDatabaseStore;
use PulkitJalan\Cache\Contracts\StoreMany;

class DatabaseStore extends IlluminateDatabaseStore implements StoreMany
{
    /**
     * Retrieve an array item from the cache by key.
     *
     * @param  array  $keys
     * @return array
     */
    public function getMany(array $keys)
    {
        $cache = $this->table()->whereIn('key', $this->prefixKeys($keys))->get()->keyBy('key');

        $cached = [];
        $expired = [];
        foreach ($keys as $key) {
            $item = $cache->get($this->getPrefix().$key);

            if (time() >= data_get($item, 'expiration')) {
                if (! is_null($item)) {
                    $expired[] = $key;
                }
                $cached[$key] = null;
                continue;
            }

            $cached[$key] = $this->encrypter->decrypt(data_get($item, 'value'));
        }

        if (! empty($expired)) {
            $this->forgetMany($expired);
        }

        return $cached;
    }

    /**
     * Store an array of items in the cache for a given number of minutes.
     *
     * @param  array  $items
     * @param  int    $minutes
     * @return void
     */
    public function putMany(array $items, $minutes)
    {
        $expiration = $this->getTime() + ($minutes * 60);

        $insert = [];
        foreach ($items as $key => $value) {
            $insert[] = [
                'key' => $this->getPrefix().$key,
                'value' => $this->encrypter->encrypt($value),
                'expiration' => $expiration,
            ];
        }

        $this->forgetMany(array_keys($items));

        $this->table()->insert($insert);
    }

    /**
     * Store an array of items in the cache indefinitely.
     *
     * @param  array  $items
     * @return void
     */
    public function foreverMany(array $items)
    {
        $this->putMany($items, 5256000);
    }

    /**
     * Remove an array of items from the cache.
     *
     * @param  array  $keys
     * @return bool
     */
    public function forgetMany(array $keys)
    {
        $this->table()->whereIn('key', $this->prefixKeys($keys))->delete();

        return array_fill_keys($keys, true);
    }

    /**
     * Prefix and array of keys with the cache prefix.
     *
     * @param array $keys
     * @return array
     */
    protected function prefixKeys(array $keys)
    {
        return array_map(function ($key) {
            return $this->getPrefix().$key;
        }, $keys);
    }
}
