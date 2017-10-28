/**
 * Novo SGA - Estatisticas
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
var App = App || {};

App.Estatisticas = {
    
    options: function (group) {
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
        
        backgroundColors: [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)',
            'rgba(75, 192, 192, 0.2)',
            'rgba(153, 102, 255, 0.2)',
            'rgba(255, 159, 64, 0.2)'
        ],
        
        gerar: function (form) {
            $.ajax({
                type: 'POST',
                url: App.url('/novosga.reports/chart'),
                data: $(form).serialize(),
                success: function (response) {
                    $('#chart-result')
                        .html('')
                        .append('<canvas id="chart-result-canvas" width="400"></canvas>');

                    var prop = {
                        id: 'chart-result-canvas',
                        dados: response.data.dados,
                        legendas: response.data.legendas,
                        titulo: response.data.titulo
                    };
                    switch (response.data.tipo) {
                        case 'pie':
                            App.Estatisticas.Grafico.pie(prop);
                        break;
                        case 'bar':
                            App.Estatisticas.Grafico.bar(prop);
                        break;
                    }
                }
            });
        },
        
        pie: function (prop) {
            var labels = [],
                data   = [],
                colors = [],
                ctx    = document.getElementById(prop.id);
            
            for (var i in prop.dados) {
                var legenda = prop.legendas && prop.legendas[i] ? prop.legendas[i] : i;
                
                colors.push(this.backgroundColors[data.length % this.backgroundColors.length]);
                data.push(parseInt(prop.dados[i]));
                labels.push(legenda);
            }
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                    }],
                    labels: labels,
                }
            });
        },
        
        bar: function (prop) {
            var labels = [],
                data   = [],
                colors = [],
                ctx    = document.getElementById(prop.id);
            
            for (var i in prop.dados) {
                var legenda = prop.legendas && prop.legendas[i] ? prop.legendas[i] : i;
                
                colors.push(this.backgroundColors[data.length % this.backgroundColors.length]);
                data.push(parseInt(prop.dados[i]));
                labels.push(legenda);
            }
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    datasets: [{
                        label: prop.titulo,
                        data: data,
                        backgroundColor: colors,
                    }],
                    labels: labels,
                }
            });
        }
    },
    
    secToTime: function (seconds) {
        var hours = Math.floor(seconds / 3600);
        var mins = Math.floor((seconds - (hours * 3600)) / 60);
        mins = mins < 10 ? '0' + mins : mins;
        var secs = Math.floor((seconds - (hours * 3600) - (mins * 60)));
        secs = secs < 10 ? '0' + secs : secs;
        return hours + ":" + mins + ":" + secs;
    },
    
    dateToSql: function (localeDate) {
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
