{{--@extends('layouts.main')--}}
@extends('layouts.app', ['page' => __('Страны'), 'pageSlug' => 'countries'])

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header">
                    <h4 class="card-title"> Список стран</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 d-md-block mb-2">
                        <a href="{{ route('activate.countries.update') }}" class="btn btn-success">Добавить/Обновить
                            данные</a>
                        <a href="{{ route('activate.countries.delete') }}" class="btn btn-danger">Удалить все</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table tablesorter " id="">
                            <thead class=" text-primary">
                            <tr>
                                <th class="text-center">ID</th>
                                <th class="text-center">ID Activate</th>
                                <th class="text-center">RU</th>
                                <th class="text-center">EN</th>
                                <th class="text-center">Icon</th>
                                <th class="text-center">Добалено</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($countries as $country)
                                <tr>
                                    <td class="text-center">{{ $country->id }}</td>
                                    <td class="text-center">{{ $country->org_id }}</td>
                                    <td class="text-center">{{ $country->name_ru }}</td>
                                    <td class="text-center">{{ $country->name_en }}</td>
                                    <td class="text-center"><img src={{ $country->image }} width="24"></td>
                                    <td class="text-center">{{ $country->created_at }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex">
                        {!! $countries->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

