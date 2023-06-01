{{--@extends('layouts.main')--}}
@extends('layouts.app', ['page' => __('Сервисы'), 'pageSlug' => 'products'])

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header">
                    <h4 class="card-title"> Список сервисов</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table tablesorter " id="">
                            <thead class=" text-primary">
                            <tr>
                                <th class="text-center">Сервис</th>
                                <th class="text-center">Количество</th>
                                <th class="text-center">Картинка</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($products as $key => $product)
                                <tr>
                                    <td class="text-center">{{ $key }}</td>
                                    <td class="text-center">{{ $product }}</td>
                                    <td class="text-center"><img class="service_img"
                                             src="https://smsactivate.s3.eu-central-1.amazonaws.com/assets/ico/{{ $key }}0.webp"
                                             width="24"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
