<?php
class Category extends FosStreaming {

    public function streams()
    {
        return $this->hasMany(Stream::class, 'cat_id', 'id');
    }
}