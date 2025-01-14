/**
 * Novo SGA - Estatisticas
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
App.Estatisticas = {
    options(group) {
        const elems = [...document.querySelectorAll(group + ' .option')];
        elems.forEach(elem => {
            const items = [...elem.querySelectorAll('input,select,textarea')]
            items.forEach(i => i.disabled = true);
            elem.style.display = 'none';
        });
        // habilitando as opções do gráfico/relatório selecionado
        const selected = document.querySelector(group + ' option:checked');
        const param = selected.dataset.opcoes;
        if (param != '') {
            const opcoes = param.split(',');
            for (var i = 0; i < opcoes.length; i++) {
                const opcaoDivs = [...document.querySelectorAll(group + ' .' + opcoes[i])];
                opcaoDivs.forEach(div => {
                    const opcaoInputs = [...div.querySelectorAll('input,select,textarea')]
                    opcaoInputs.forEach(i => i.disabled = false);
                    div.style.display = 'block';
                });
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
        async gerar(form) {
            const resp = await fetch(App.url('/novosga.reports/chart'), {
                method: 'post',
                body: new FormData(form),
            });
            const json = await resp.json();
            const data = json.data;

            const frame = document.getElementById('chart-result');
            frame.innerHTML = '<canvas id="chart-result-canvas" width="400"></canvas>';

            var prop = {
                id: 'chart-result-canvas',
                dados: data.dados,
                legendas: data.legendas,
                titulo: data.titulo
            };
            switch (data.tipo) {
                case 'pie':
                    App.Estatisticas.Grafico.pie(prop);
                break;
                case 'bar':
                    App.Estatisticas.Grafico.bar(prop);
                break;
            }
        },
        
        pie(prop) {
            const labels = [];
            const data = [];
            const colors = [];
            const ctx = document.getElementById(prop.id);
            
            for (let i in prop.dados) {
                const legenda = prop.legendas && prop.legendas[i] ? prop.legendas[i] : i;
                colors.push(this.backgroundColors[data.length % this.backgroundColors.length]);
                data.push(parseInt(prop.dados[i]));
                labels.push(legenda);
            }
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [
                        {
                            data: data,
                            backgroundColor: colors,
                        }
                    ],
                    labels: labels,
                }
            });
        },

        bar(prop) {
            const labels = [];
            const data = [];
            const colors = [];
            const ctx = document.getElementById(prop.id);

            for (let i in prop.dados) {
                const legenda = prop.legendas && prop.legendas[i] ? prop.legendas[i] : i;
                colors.push(this.backgroundColors[data.length % this.backgroundColors.length]);
                data.push(parseInt(prop.dados[i]));
                labels.push(legenda);
            }
            
            new Chart(ctx, {
                type: 'bar',
                options: {
                    scales: {
                        yAxes: [
                            {
                                ticks: {
                                    callback: function(label, index, labels) {
                                        return App.Estatisticas.secToTime(label);
                                    }
                                }
                            }
                        ],
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return App.Estatisticas.secToTime(tooltipItem.yLabel);
                            }
                        }
                    }
                },
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
    
    secToTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var mins = Math.floor((seconds - (hours * 3600)) / 60);
        mins = mins < 10 ? '0' + mins : mins;
        var secs = Math.floor((seconds - (hours * 3600) - (mins * 60)));
        secs = secs < 10 ? '0' + secs : secs;
        return (hours > 0 ? hours + ":" : '') + mins + ":" + secs;
    },
    
    dateToSql(localeDate) {
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
