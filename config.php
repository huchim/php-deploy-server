<?php

$pipelines = Huchim\Configuration::getPipelines();

$saoPipeline = new \Huchim\Pipeline("sao");

// Agregar carpetas que no serán tomadas en cuenta.
$saoPipeline->addExclude(".gitignore")
            ->addExclude(".git")
            ->addExclude(".vscode");

$saoPipeline->addStage("development", "H:\\git_projects3\\avance-obra\\api");
$saoPipeline->addStage("production", "/home/huchimco/api.huchim.com/sao", ["huchim"]);

$pipelines->add($saoPipeline);