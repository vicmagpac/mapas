<?php

namespace OpportunityAccountability;

use OpportunityPhases\Module as PhasesModule;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Project;
use MapasCulturais\Entities\Registration;
use MapasCulturais\i;
use MapasCulturais\ApiQuery;

class Module extends \MapasCulturais\Module
{
    function _init()
    {
        $app = App::i();

        $registration_repository = $app->repo('Registration');

        // impede que a fase de prestação de contas seja considerada a última fase da oportunidade
        $app->hook('entity(Opportunity).getLastCreatedPhase:params', function(Opportunity $base_opportunity, &$params) {
            $params['isAccountabilityPhase'] = 'NULL()';
        });

        $app->hook('entity(Registration).get(accountabilityPhase)', function(&$value) use ($registration_repository){
            $opportunity = $this->opportunity->parent ?: $this->opportunity;
            $accountability_phase = $opportunity->accountabilityPhase;

            $value = $registration_repository->findOneBy([
                'opportunity' => $accountability_phase,
                'number' => $this->number
             ]);
        });

        // na publicação da última fase, cria os projetos
        $app->hook('entity(Opportunity).publishRegistration', function (Registration $registration) use($app) {
            if (! $this instanceof \MapasCulturais\Entities\ProjectOpportunity) {
                return;
            }

            if (!$this->isLastPhase) {
                return;
            }

            $project = new Project;
            $project->status = 0;
            $project->type = 0;
            $project->name = $registration->projectName ?: ' ';
            $project->parent = $app->repo('Project')->find($this->ownerEntity->id);
            $project->isAccountability = true;
            $project->owner = $registration->owner;

            $project->registration = $registration->firstPhase;
            $project->opportunity = $this->parent ?: $this;

            $project->save(true);

            $app->applyHookBoundTo($this, $this->getHookPrefix() . '.createdAccountabilityProject', [$project]);

        });

        $app->hook('template(project.<<single|edit>>.tabs):end', function(){
            $project = $this->controller->requestedEntity;

            if ($project->isAccountability) {
                if ($project->canUser('@control')) {
                    $this->part('accountability/project-tab');
                }
            }
        },1000);

        $app->hook('template(project.<<single|edit>>.tabs-content):end', function(){
            $project = $this->controller->requestedEntity;

            if ($project->isAccountability) {
                if($project->canUser('@control')){
                    $this->part('accountability/project-tab-content');
                }
            }
        },1000);

        // Adidiona o checkbox haverá última fase
        $app->hook('template(opportunity.edit.new-phase-form):end', function () use ($app) {
            $app->view->part('widget-opportunity-accountability', ['opportunity' => '']);
        });

        $self = $this;
        $app->hook('entity(Opportunity).insert:after', function () use ($app, $self) {

            $opportunityData = $app->controller('opportunity');

            if ($this->isLastPhase && isset($opportunityData->postData['hasAccountability']) && $opportunityData->postData['hasAccountability']) {
                
                $self->createAccountabilityPhase($this->parent);
            }
        });


        $app->hook('template(project.<<single|edit>>.header-content):before', function () {
            $project = $this->controller->requestedEntity;

            if ($project->isAccountability) {
                $this->part('accountability/project-opportunity', ['opportunity' => $project->opportunity]);
            }
        });

        // 
        $app->hook('template(opportunity.single.tabs):end', function () use ($app) {

            // $entity = $this->controller->requestedEntity;
            // $this->part('singles/opportunity-projects--tab', ['entity' => $entity]);

        });

        //
        $app->hook('template(opportunity.single.tabs-content):end', function () use ($app) {

            // $entity = $this->controller->requestedEntity;
            // $this->part('singles/opportunity-projects', ['entity' => $entity]);

        });
        
        /**
         * Substituição dos seguintes termos 
         * - avaliação por parecer
         * - avaliador por parecerista
         * - inscrição por prestação de contas
         */
        $replacements = [
            i::__('Nenhuma avaliação enviada') => i::__('Nenhum parecer técnico enviado'),
            i::__('Configuração da Avaliação') => i::__('Configuração do Parecer Técnico'),
            i::__('Comissão de Avaliação') => i::__('Comissão de Pareceristas'),
            i::__('Inscrição') => i::__('Prestacão de Contas'),
            i::__('inscrição') => i::__('prestacão de contas'),
            // inscritos deve conter somente a versão com o I maiúsculo para não quebrar o JS
            i::__('Inscritos') => i::__('Prestacoes de Contas'),
            i::__('Inscrições') => i::__('Prestações de Contas'),
            i::__('inscrições') => i::__('prestações de contas'),
            i::__('Avaliação') => i::__('Parecer Técnico'),
            i::__('avaliação') => i::__('parecer técnico'),
            i::__('Avaliações') => i::__('Pareceres'),
            i::__('avaliações') => i::__('pareceres'),
            i::__('Avaliador') => i::__('Parecerista'),
            i::__('avaliador') => i::__('parecerista'),
            i::__('Avaliadores') => i::__('Pareceristas'),
            i::__('avaliadores') => i::__('pareceristas'),
        ];

        $app->hook('view.partial(singles/opportunity-<<tabs|evaluations--admin--table|registrations--tables--manager|evaluations--committee>>):after', function($template, &$html) use($replacements) {
            $phase = $this->controller->requestedEntity;
            if ($phase->isAccountabilityPhase) {
                $html = str_replace(array_keys($replacements), array_values($replacements), $html);
            }
         });        
    }

    function register()
    {
        $app = App::i();
        $opportunity_repository = $app->repo('Opportunity');
        $registration_repository = $app->repo('Registration');

        $this->registerProjectMetadata('isAccountability', [
            'label' => i::__('Indica que o projeto é vinculado à uma inscrição aprovada numa oportunidade'),
            'type' => 'boolean',
            'default' => false
        ]);

        $this->registerProjectMetadata('opportunity', [
            'label' => i::__('Oportunidade da prestação de contas vinculada a este projeto'),
            'type' => 'Opportunity',
            'serialize' => function (Opportunity $opportunity) {
                return $opportunity->id;
            },
            'unserialize' => function ($opportunity_id, $opportunity) use($opportunity_repository, $app) {

                if ($opportunity_id) {
                    return $opportunity_repository->find($opportunity_id);
                } else {
                    return null;
                }
            }
        ]);

        $this->registerProjectMetadata('registration', [
            'label' => i::__('Inscrição da oportunidade da prestação de contas vinculada a este projeto (primeira fase)'),
            'type' => 'number',
            'private' => true,
            'serialize' => function (Registration $registration) {
                return $registration->id;
            },
            'unserialize' => function ($registration_id) use($registration_repository) {
                if ($registration_id) {
                    return $registration_repository->find($registration_id);
                } else {
                    return null;
                }
            }
        ]);

        $this->registerOpportunityMetadata('isAccountabilityPhase', [
            'label' => i::__('Indica se a oportunidade é uma fase de prestação de contas'),
            'type' => 'boolean',
            'default' => false
        ]);

        $this->registerOpportunityMetadata('accountabilityPhase', [
            'label' => i::__('Indica se a oportunidade é uma fase de prestação de contas'),
            'type' => 'Opportunity',
            'serialize' => function (Opportunity $opportunity) {
                return $opportunity->id;
            },
            'unserialize' => function ($opportunity_id, $opportunity) use($opportunity_repository) {
                if ($opportunity_id) {
                    return $opportunity_repository->find($opportunity_id);
                } else {
                    return null;
                }
            }
        ]);
    }

    // Migrar essa função para o módulo "Opportunity phase"
    function createAccountabilityPhase(Opportunity $parent)
    {

        $opportunity_class_name = $parent->getSpecializedClassName();

        $last_phase = \OpportunityPhases\Module::getLastCreatedPhase($parent);

        $phase = new $opportunity_class_name;

        $phase->status = Opportunity::STATUS_DRAFT;
        $phase->parent = $parent;
        $phase->ownerEntity = $parent->ownerEntity;

        $phase->name = i::__('Prestação de Contas');
        $phase->registrationCategories = $parent->registrationCategories;
        $phase->shortDescription = i::__('Descrição da Prestação de Contas');
        $phase->type = $parent->type;
        $phase->owner = $parent->owner;
        $phase->useRegistrations = true;
        $phase->isOpportunityPhase = true;
        $phase->isAccountabilityPhase = true;

        $_from = $last_phase->registrationTo ? clone $last_phase->registrationTo : new \DateTime;
        $_to = $last_phase->registrationTo ? clone $last_phase->registrationTo : new \DateTime;
        $_to->add(date_interval_create_from_date_string('1 days'));

        $phase->registrationFrom = $_from;
        $phase->registrationTo = $_to;

        $phase->save(true);
    }
}
