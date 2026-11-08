<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Wishlist
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('status'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    {{ session('status') }}
                </div>
            @endif

            @if($properties->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-10 text-center border border-gray-200">
                    <p class="text-gray-500 text-lg">You haven't saved any properties to your wishlist yet.</p>
                    <a href="/dashboard" class="mt-4 inline-block text-blue-600 hover:underline">Browse properties</a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($properties as $property)
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm p-5">
                            <h3 class="text-lg font-bold text-gray-900">{{ $property->title }}</h3>
                            <p class="text-gray-600 mt-2">${{ $property->price }} / night</p>
                            
                            <form action="{{ route('wishlist.toggle', ['propertyId' => $property->id]) }}" method="POST" class="mt-6">
                                @csrf
                                <button type="submit" class="w-full bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-md hover:bg-red-100 transition-colors font-medium">
                                    Remove from Wishlist
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>