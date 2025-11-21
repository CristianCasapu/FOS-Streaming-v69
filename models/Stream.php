<?php
class Stream extends FosStreaming {

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'cat_id');
    }

    public function transcode()
    {
        return $this->hasOne(Transcode::class, 'id', 'trans_id');
    }

    public function getStatusLabelAttribute()
    {
        $return = [];
        $return['label'] = 'danger';
        $return['text'] = 'STOPPED';

        if ($this->status == '1') {
            $return['label'] = 'success';
            $return['text'] = 'RUNNING';
        } else if ($this->status == '2') {
            $return['label'] = 'danger';
            $return['text'] = 'ERROR';
        }

        return $return;
    }
}