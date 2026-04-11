<h3>{{$data['title']}}</h3>
{!! $data['filter'] ?? '' !!}
@if(!($data['hideChart'] ?? 0))
    <canvas id="myChart{{$data['count']??1}}" height="100px"></canvas>
@endif
<br>
<table>
    <tr style="max-width: 300px;">
    @for ($i =0 ; $i < $data['rowTotal']; $i++)
        @foreach($data['tableTitle'] ?? [] as $key=>$value)
        <th>{{$value}}</th>
        @endforeach
    @endfor
    </tr>
    <?php $i=0 ?>
    @foreach($data['total'] ?? [] as $key=>$value)
    <?php $i+=1;?>
    @if(($i % $data['rowTotal'])==1)
        <tr style="max-width: 300px;">
    @endif
        <td style="max-width: 300px;">{!!$key!!}</td>
        @foreach($value ?? [] as $key2=>$value2)
            <td style="max-width: 300px;">{!!$value2!!}</td>
        @endforeach
    @if(($i % $data['rowTotal'])==0)
        </tr>
    @endif
    @endforeach
</table>

<script>
$(document).ready(function() {
    @if(!($data['hideChart'] ?? 0))
        const data{{$data['count']??1}} = {
            labels: JSON.parse('{!!json_encode($data['labels'] ?? [])!!}'),
            datasets: [
                @foreach($data['datas'] ?? [] as $key=>$value)
                    <?php 
                    $color = $value['color'];
                    unset($value['color']);
                    ?>
                    {
                        label: '{{$key}}',
                        data:  JSON.parse('{!!json_encode($value)!!}'),
                        backgroundColor: '{{$color}}',
                        borderColor: '{{$color}}',
                    },
                @endforeach
            ]
        };

        const config{{$data['count']??1}} = {
            type: 'line',
            data: data{{$data['count']??1}},
            options: {
                {{$data['option']??''}}
            }
        };

        const myChart{{$data['count']??1}} = new Chart(
            document.getElementById('myChart{{$data['count']??1}}'),
            config{{$data['count']??1}}
        );
    @endif
    {!! $data['script'] ?? '' !!}
});
</script>
