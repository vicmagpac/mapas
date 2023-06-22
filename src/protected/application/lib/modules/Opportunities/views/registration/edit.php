<?php
/**
 * @var \MapasCulturais\Themes\BaseV2\Theme $this
 * @var \MapasCulturais\App $app
 * @var \MapasCulturais\Entities\Registration $entity
 */

use MapasCulturais\i;

$this->layout = 'registrations';

$this->import('
    mc-breadcrumb
    mc-card
    mc-container
    mc-icon
    opportunity-header
    registration-actions
    registration-related-agents
    registration-related-space
    registration-related-project
    registration-steps
    select-entity
    v1-embed-tool
');

$opportunity = $entity->opportunity;

$breadcrumb = [
    ['label' => i::__('Oportunidades'), 'url' => $app->createUrl('panel', 'opportunities')],
    ['label' => $opportunity->firstPhase->name, 'url' => $app->createUrl('opportunity', 'single', [$opportunity->firstPhase->id])],
];

if (!$opportunity->isFirstPhase) {
    $breadcrumb[] = ['label' => $opportunity->name, 'url' => $app->createUrl('opportunity', 'single', [$opportunity->id])];
}

$breadcrumb[] = ['label' => i::__('Formulário')];

$this->breadcrumb = $breadcrumb;

/**
 * @todo registration-form
 */

 $this->import('
    opportunity-header
    registration-info
    registration-steps
');
?>

<div class="main-app registration edit">
    <mc-breadcrumb></mc-breadcrumb>
    <opportunity-header :opportunity="entity.opportunity"></opportunity-header>

    <div class="registration__title">
        <?= i::__('Formulário de inscrição') ?>
    </div>

    <div class="registration__content">
        <div class="registration__steps">
            <registration-steps></registration-steps>
        </div>

        <mc-container>
            <main class="grid-12">
                <registration-info :registration="entity" classes="col-12"></registration-info>                
                <section class="section">
                    <div class="section__title" id="main-info">
                        <?= i::__('Informações básicas') ?>
                    </div>
                    <div class="section__content">                         
                        <div class="card owner">                            
                            <div class="card__title"> 
                                <?= i::__('Agente responsável') ?> 
                            </div>
                            <div class="card__content">
                                <div class="owner">
                                    <div class="owner__image">
                                        <img v-if="entity.owner.files?.avatar" :src="entity.owner.files?.avatar?.transformations?.avatarSmall?.url" />
                                        <mc-icon v-if="!entity.owner.files?.avatar" name="image"></mc-icon>
                                    </div>
                                    <div class="owner__name">
                                        {{entity.owner.name}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <registration-related-agents :registration="entity"></registration-related-agents>
                        <registration-related-space :registration="entity"></registration-related-space>
                        <registration-related-project :registration="entity"></registration-related-project>
                    </div>
                </section>

                <section class="section">
                    <v1-embed-tool iframe-id="registration-form" route="registrationform" :id="entity.id"></v1-embed-tool>
                </section>
            </main>

            <aside>
                <registration-actions :registration="entity"></registration-actions>
            </aside>
        </mc-container>
    </div>
</div>