/**
 * Novo SGA - Estatisticas
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
var App = App || {};

App.Estatisticas = {
    
    options: function(group) {
        var elems = $(group + ' .option');
        elems.find(':input').prop('disabled', true);
        elems.hide();
        // habilitando as opções do gráfico/relatório selecionado
        var param = $(group + ' option:selected').data('opcoes');
        if (param != '') {
            var opcoes = param.split(',');
            for (var i = 0; i < opcoes.length; i++) {
                elems = $(group + ' .' + opcoes[i]);
                elems.find(':input').prop('disabled', false);
                elems.show();
            }
        }
    },
    
    Grafico: {
        
        gerar: function() {
            var id = $('#chart-id').val();
            if (id > 0) {
                var dtIni = $('#chart-dataInicial').val();
                var dtFim = $('#chart-dataFinal').val();
                App.ajax({
                    url: App.url('/novosga.reports/chart/') + id,
                    data: {
                        grafico: id, 
                        inicial: App.Estatisticas.dateToSql(dtIni), 
                        final: App.Estatisticas.dateToSql(dtFim)
                    },
                    success: function(response) {
                        var prop = {
                            id: 'chart-result', 
                            dados: response.data.dados,
                            legendas: response.data.legendas,
                            titulo: response.data.titulo + ' (' + dtIni + ' - ' + dtFim + ')'
                        };
                        switch (response.data.tipo) {
                        case 'pie':
                            App.Estatisticas.Grafico.pie(prop);
                            break;
                        case 'bar':
                            App.Estatisticas.Grafico.bar(prop);
                            break;
                        }
                        $(window).scrollTop($('#chart-result').position().top);
                    }
                });
            }
        },
        
        change: function(elem) {
            if (elem.val() > 0) {
                // desabilitando as opções
                App.Estatisticas.options('#tab-graficos');
            }
        },
        
        pie: function(prop) {
            var series = [];
            for (var j in prop.dados) {
                var legenda = prop.legendas && prop.legendas[j] ? prop.legendas[j] : j;
                series.push([legenda, parseInt(prop.dados[j])]);
            }
            new Highcharts.Chart({
                chart: {
                    renderTo: prop.id,
                    type: 'pie'
                },
                title: { 
                    text: prop.titulo 
                },
                plotOptions: {
                    pie: {
                        showInLegend: true,
                        dataLabels: {
                            enabled: true,
                            formatter: function() {
                                return '<b>' + this.point.name + '</b>: ' + Math.round(this.point.total * this.point.percentage / 100);
                            }
                        }
                    }
                },
                series: [{
                    type: 'pie',
                    name: prop.titulo,
                    data: series
                }],
                exporting: {
                    enabled: true,
                    sourceWidth: 1024,
                    sourceHeight: 800
                }
            });
        },
        
        bar: function(prop) {
            var series = [];
            var categories = [];
            for (var j in prop.dados) {
                var legenda = prop.legendas && prop.legendas[j] ? prop.legendas[j] : j;
                series.push({
                    name: legenda, 
                    data: [parseInt(prop.dados[j])]
                });
                categories.push(legenda);
            }
            new Highcharts.Chart({
                chart: {
                    renderTo: prop.id,
                    type: 'bar'
                },
                title: { 
                    text: prop.titulo 
                },
                xAxis: {
                    categories: categories,
                    title: {
                        text: null
                    }
                },
                // TODO: informar no response o tipo de tooltip (abaixo esta fixo formatando tempo)
                tooltip: {
                    formatter: function() {
                        return this.series.name + ': ' + App.Estatisticas.secToTime(this.y);
                    }
                },
                series: series
            });
        }
        
    },
    
    Relatorio: {
        
        gerar: function() {
            $('#report-hidden-inicial').val(App.Estatisticas.dateToSql($('#report-dataInicial').val()));
            $('#report-hidden-final').val(App.Estatisticas.dateToSql($('#report-dataFinal').val()));
            return true;
        },
        
        change: function(elem) {
            if (elem.val() > 0) {
                // desabilitando as opções
                App.Estatisticas.options('#tab-relatorios');
            }
        }
        
    },
            
    secToTime: function(seconds) {
        var hours = Math.floor(seconds / 3600);
        var mins = Math.floor((seconds - (hours * 3600)) / 60);
        mins = mins < 10 ? '0' + mins : mins;
        var secs = Math.floor((seconds - (hours * 3600) - (mins * 60)));
        secs = secs < 10 ? '0' + secs : secs;
        return hours + ":" + mins + ":" + secs;
    },
    
    dateToSql: function(localeDate) {
        if (localeDate && localeDate != "") {
            var datetime = localeDate.split(' ');
            var date = datetime[0].split('/');
            var time = '';
            // date i18n
            var format = App.dateFormat.toLowerCase().split("/");
            var sqlDate = [];
            for (var i = 0; i < format.length; i++) {
                switch (format[i]) {
                case 'd':
                    sqlDate[2] = date[i];
                    break;
                case 'm':
                    sqlDate[1] = date[i];
                    break;
                case 'y':
                    sqlDate[0] = date[i];
                    break;
                }
            }
            if (datetime.length > 1) {
                time = ' ' + datetime[1];
            }
            return sqlDate.join('-') + time;
        }
        return "";
    },
    
};
