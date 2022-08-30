<?php
use MapasCulturais\i;

$this->import('mc-icon mc-link');
?>

<div class="entity-card">
    <div class="entity-card__header">
        <div class="entity-card__header--image">
            <img v-if="event.files?.avatar" :src="event.files?.avatar?.transformations?.avatarMedium.url" />
            <mc-icon v-else name="event"></mc-icon>
        </div>
        
        <div class="entity-card__header--title">
            <label class="entity-card__header--title-title"> 
                {{event.name}}
            </label>
        </div>
        <div class="entity-card__header--subTitle">
            <label class="entity-card__header--title-subTitle"> 
                {{event.subTitle}}
            </label>
        </div>
    </div>

    <div class="entity-card__content">
        <div><mc-icon name="event"></mc-icon> {{occurrence.starts.date('long')}} às {{occurrence.starts.time()}}</div>
        <div><mc-link :entity="space" icon></mc-link> - {{space.endereco}}</div>
        <div><?= i::__('Classificação') ?>: {{event.classificacaoEtaria}}</div>
        <div v-if="occurrence.price"><?= i::__('Entrada') ?>: {{occurrence.price}}</div>

        <div class="entity-card__content--terms">            
            <div v-if="tags" class="entity-card__content--terms-tag">
                <label class="tag__title">
                    <?php i::_e('Tags:') ?> ({{event.terms.tag.length}}):
                </label>
                <p :class="['terms', 'event__color']"> {{tags}} </p>
            </div>

            <div v-if="linguagens" class="entity-card__content--terms-linguagem">
                <label class="linguagem__title">
                    <?php i::_e('linguagens:') ?> ({{event.terms.linguagem.length}}):
                </label>
                <p :class="['terms', 'event__color']"> {{linguagens}} </p>
            </div>
        </div>
    </div>

    <div class="entity-card__footer">
        <div class="entity-card__footer--info">
            <div v-if="seals" class="seals">
                <label class="seals__title"> 
                    <?php i::_e('Selos') ?> ({{event.seals.length}}):
                </label>
                <div v-for="seal in seals" class="seals__seal"></div>
                <div v-if="seals.length == 2" class="seals__seal more">+1</div>
            </div>
        </div>

        <div class="entity-card__footer--action">
            <a :href="event.singleUrl" class="button button--primary button--large button--icon"> 
                <?php i::_e('Acessar') ?> 
                <mc-icon name="access"></mc-icon> 
            </a>
        </div>
    </div>

</div>