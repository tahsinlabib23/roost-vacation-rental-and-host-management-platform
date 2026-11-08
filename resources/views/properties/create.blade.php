<div class="taxonomy-section mt-8">
    <h3 class="text-xl font-semibold mb-4">Amenities & Safety Features</h3>
    
    @foreach($amenityCategories as $categoryKey => $category)
        <div class="amenity-category mb-6 border-b pb-4">
            <h4 class="font-medium text-gray-800 mb-3">{{ $category['label'] }}</h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($category['items'] as $itemKey => $itemLabel)
                    <div class="flex items-center">
                        <input type="checkbox" 
                               name="amenities[]" 
                               id="amenity_{{ $itemKey }}" 
                               value="{{ $itemKey }}" 
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="amenity_{{ $itemKey }}" class="ml-2 text-sm text-gray-700">
                            {{ $itemLabel }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>