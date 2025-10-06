<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-color: {{ $config['primary_color'] ?? '#3b82f6' }};
        }
        .primary-bg { background-color: var(--primary-color); }
        .primary-text { color: var(--primary-color); }
        .primary-border { border-color: var(--primary-color); }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="primary-bg text-white shadow-lg">
            <div class="container mx-auto px-6 py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $title }}</h1>
                        <p class="text-blue-100 mt-2">{{ $description }}</p>
                    </div>
                    @if($config['logo_url'])
                        <img src="{{ $config['logo_url'] }}" alt="Logo" class="h-12">
                    @endif
                </div>
            </div>
        </header>

        <div class="container mx-auto px-6 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Navigation</h3>
                        <nav class="space-y-4">
                            @if(!empty($routes) && $routes->count() > 0)
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">API Endpoints</h4>
                                    <div class="space-y-1">
                                        @foreach($routes as $route)
                                            <a href="#route-{{ md5($route['uri']) }}" 
                                               class="block px-3 py-2 text-xs text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                                                <span class="font-mono">{{ strtoupper(implode('|', array_diff($route['methods'], ['HEAD']))) }}</span>
                                                <span class="ml-1">{{ $route['uri'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Data Classes</h4>
                                <div class="space-y-1">
                                    @foreach($documentation as $className => $data)
                                        <a href="#{{ $data['name'] }}" 
                                           class="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                                            {{ $data['name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <!-- API Routes Section -->
                    @if(!empty($routes) && $routes->count() > 0)
                        <div class="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
                            <div class="primary-bg text-white px-6 py-4">
                                <h2 class="text-2xl font-bold">API Endpoints</h2>
                                <p class="text-blue-100 text-sm mt-1">Routes using Laravel Data classes</p>
                            </div>
                            <div class="p-6">
                                <div class="space-y-6">
                                    @foreach($routes as $route)
                                        <div id="route-{{ md5($route['uri']) }}" class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center space-x-3">
                                                    @foreach(array_diff($route['methods'], ['HEAD']) as $method)
                                                        <span class="px-2 py-1 text-xs font-mono font-bold rounded
                                                            @if($method === 'GET') bg-green-100 text-green-800
                                                            @elseif($method === 'POST') bg-blue-100 text-blue-800
                                                            @elseif($method === 'PUT' || $method === 'PATCH') bg-yellow-100 text-yellow-800
                                                            @elseif($method === 'DELETE') bg-red-100 text-red-800
                                                            @else bg-gray-100 text-gray-800
                                                            @endif">
                                                            {{ $method }}
                                                        </span>
                                                    @endforeach
                                                    <code class="text-lg font-mono">{{ $route['uri'] }}</code>
                                                </div>
                                            </div>
                                            
                                            @if($route['description'])
                                                <p class="text-gray-600 mb-3">{{ $route['description'] }}</p>
                                            @endif

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                @if($route['request_data'])
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Request Data</h4>
                                                        <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ class_basename($route['request_data']) }}</code>
                                                        <a href="#{{ class_basename($route['request_data']) }}" class="text-blue-600 hover:text-blue-800 text-xs ml-2">View Details</a>
                                                    </div>
                                                @endif

                                                @if($route['response_data'])
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Response Data</h4>
                                                        <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ class_basename($route['response_data']) }}</code>
                                                        <a href="#{{ class_basename($route['response_data']) }}" class="text-blue-600 hover:text-blue-800 text-xs ml-2">View Details</a>
                                                    </div>
                                                @endif
                                            </div>

                                            @if(!empty($route['parameters']))
                                                <div class="mt-4">
                                                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Route Parameters</h4>
                                                    <div class="space-y-1">
                                                        @foreach($route['parameters'] as $param)
                                                            <div class="flex items-center space-x-2 text-sm">
                                                                <code class="bg-gray-100 px-2 py-1 rounded">{{ $param['name'] }}</code>
                                                                <span class="text-gray-500">{{ $param['type'] }}</span>
                                                                @if($param['required'])
                                                                    <span class="text-red-600 text-xs">required</span>
                                                                @else
                                                                    <span class="text-gray-500 text-xs">optional</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Data Classes Section -->
                    @foreach($documentation as $className => $data)
                        <div id="{{ $data['name'] }}" class="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
                            <!-- Class Header -->
                            <div class="primary-bg text-white px-6 py-4">
                                <h2 class="text-2xl font-bold">{{ $data['name'] }}</h2>
                                <p class="text-blue-100 text-sm mt-1">{{ $data['namespace'] }}</p>
                                @if($data['description'])
                                    <p class="text-blue-100 mt-2">{{ $data['description'] }}</p>
                                @endif
                            </div>

                            <div class="p-6">
                                <!-- Properties -->
                                <div class="mb-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Properties</h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full border border-gray-200 rounded-lg">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Name</th>
                                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Type</th>
                                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Nullable</th>
                                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach($data['properties'] as $property)
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                            {{ $property['name'] }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm">
                                                            <code class="bg-gray-100 px-2 py-1 rounded text-xs">
                                                                {{ $property['type'] }}
                                                            </code>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm">
                                                            @if($property['nullable'])
                                                                <span class="text-yellow-600">Yes</span>
                                                            @else
                                                                <span class="text-green-600">No</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-600">
                                                            {{ $property['description'] ?: 'No description' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Example -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Example</h3>
                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-green-400 text-sm"><code>{!! json_encode($data['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-8 mt-16">
            <div class="container mx-auto px-6 text-center">
                <p class="text-gray-400">
                    Generated by <span class="primary-text font-semibold">What's Up Doc</span>
                </p>
            </div>
        </footer>
    </div>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
