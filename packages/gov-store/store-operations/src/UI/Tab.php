<?php

namespace GovStore\StoreOperations\UI;

class Tab
{
    public string $id;
    public string $title;
    public string $icon;
    public string $ajaxUrl;

    public function __construct(string $id, string $title, string $ajaxUrl, string $icon = '')
    {
        $this->id = $id;
        $this->title = $title;
        $this->ajaxUrl = $ajaxUrl;
        $this->icon = $icon;
    }
}
