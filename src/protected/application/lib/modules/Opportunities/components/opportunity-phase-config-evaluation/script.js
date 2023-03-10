app.component('opportunity-phase-config-evaluation' , {
    template: $TEMPLATES['opportunity-phase-config-evaluation'],

    setup() {
        const text = Utils.getTexts('opportunity-phase-config-evaluation');
        return { text };
    },

    props: {
        phases: {
            type: Array,
            required: true
        },
        phase: {
            type: Entity,
            required: true
        }
    },

    computed: {
        index() {
            return this.phases.indexOf(this.phase);
        },

        previousPhase() {
            return this.phases[this.index - 1];
        },

        nextPhase() {
            return this.phases[this.index + 1];
        },

        minDate() {
            if (this.previousPhase.__objectType == 'evaluationmethodconfiguration') {
                // fase anterior é uma fase de avaliação
                return this.previousPhase.evaluationTo;
            } else {
                // fase anterior é uma fase de coleta de dados
                return this.previousPhase.registrationFrom;
            }
        },

        maxDate() {
            if (this.nextPhase.isLastPhase) {
                // próxima fase é de publicação de resultado
                return this.nextPhase.publishTimestamp;
            } else if(this.nextPhase.__objectType == 'opportunity') {
                // próxima fase é de coleta de dados
                return this.nextPhase.registrationFrom;
            } else {
                // próxima fase avalição
                return this.nextPhase.evaluationFrom;
            }
        },

        categories(){
            return this.phase.registrationCategories instanceof Array ?  this.phase.registrationCategories : [];
        }
    },

    methods: {
        async deletePhase (event, item, index) {
            const messages = useMessages();
            try {
                await item.destroy();
                this.phases.splice(index, 1);
            } catch (e) {
                messages.error(this.text('nao foi possivel remover fase'));
            }

        }
    }
});