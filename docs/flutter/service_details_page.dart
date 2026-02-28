import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

class GlamoPalette {
  static const Color primary = Color(0xFF5A0E24);
  static const Color secondary = Color(0xFF76153C);
  static const Color bg = Colors.white;
  static const Color soft = Color(0xFFFBF6F8);
  static const Color text = Color(0xFF1B1B1B);
  static const Color muted = Color(0xFF6B6B6B);
  static const Color border = Color(0x245A0E24);
}

String tzs(num value) {
  final text = value.round().toString();
  final withComma = text.replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
  return 'TSh $withComma';
}

Map<String, dynamic> _asMap(dynamic v) => v is Map<String, dynamic>
    ? v
    : (v is Map ? v.map((k, dynamic e) => MapEntry('$k', e)) : <String, dynamic>{});
List<dynamic> _asList(dynamic v) => v is List ? v : <dynamic>[];
String _s(dynamic v) => v?.toString() ?? '';
double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse(_s(v)) ?? 0;
int _i(dynamic v) => v is int ? v : (v is num ? v.toInt() : int.tryParse(_s(v)) ?? 0);
bool _b(dynamic v) {
  if (v is bool) return v;
  final t = _s(v).toLowerCase();
  return t == '1' || t == 'true' || t == 'yes';
}

class ServicePrice {
  ServicePrice({
    required this.service,
    required this.materials,
    required this.usagePercent,
    required this.usage,
    required this.travel,
    required this.total,
    required this.hasLocation,
    required this.distanceKm,
  });

  final double service;
  final double materials;
  final double usagePercent;
  final double usage;
  final double travel;
  final double total;
  final bool hasLocation;
  final double? distanceKm;

  factory ServicePrice.fromJson(Map<String, dynamic> json) => ServicePrice(
        service: _d(json['service']),
        materials: _d(json['materials']),
        usagePercent: _d(json['usage_percent']),
        usage: _d(json['usage']),
        travel: _d(json['travel']),
        total: _d(json['total']),
        hasLocation: _b(json['has_location']),
        distanceKm: json['distance_km'] == null ? null : _d(json['distance_km']),
      );
}

class ServiceDetails {
  ServiceDetails({
    required this.id,
    required this.name,
    required this.shortDesc,
    required this.imageUrl,
    required this.gallery,
    required this.categoryName,
    required this.providersTotal,
    required this.price,
  });

  final int id;
  final String name;
  final String? shortDesc;
  final String imageUrl;
  final List<String> gallery;
  final String categoryName;
  final int? providersTotal;
  final ServicePrice price;

  factory ServiceDetails.fromJson(Map<String, dynamic> json) => ServiceDetails(
        id: _i(json['id']),
        name: _s(json['name']),
        shortDesc: _s(json['short_desc']).trim().isEmpty ? null : _s(json['short_desc']),
        imageUrl: _s(json['image_url']),
        gallery: _asList(json['gallery']).map((e) => _s(e)).where((e) => e.trim().isNotEmpty).toList(),
        categoryName: _s(_asMap(json['category'])['name']),
        providersTotal: json['providers_total'] == null ? null : _i(json['providers_total']),
        price: ServicePrice.fromJson(_asMap(json['price'])),
      );
}

class ProviderItem {
  ProviderItem({
    required this.id,
    required this.displayName,
    required this.profileImageUrl,
    required this.ratingAvg,
    required this.distanceKm,
    required this.totalPrice,
  });

  final int id;
  final String displayName;
  final String profileImageUrl;
  final double? ratingAvg;
  final double? distanceKm;
  final double totalPrice;

  factory ProviderItem.fromJson(Map<String, dynamic> json) => ProviderItem(
        id: _i(json['id']),
        displayName: _s(json['display_name']),
        profileImageUrl: _s(json['profile_image_url']),
        ratingAvg: json['rating_avg'] == null ? null : _d(json['rating_avg']),
        distanceKm: json['distance_km'] == null ? null : _d(json['distance_km']),
        totalPrice: _d(_asMap(json['pricing'])['total']),
      );
}

class CatalogApi {
  CatalogApi({required this.baseUrl, http.Client? client})
      : _client = client ?? http.Client(),
        _base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;

  final String baseUrl;
  final String _base;
  final http.Client _client;

  Future<ServiceDetails> fetchService({
    required int serviceId,
    double? lat,
    double? lng,
  }) async {
    final data = await _get('/services/$serviceId', query: _locationQuery(lat, lng));
    return ServiceDetails.fromJson(_asMap(data['service']));
  }

  Future<List<ProviderItem>> fetchProviders({
    required int serviceId,
    double? lat,
    double? lng,
    int limit = 20,
  }) async {
    final query = _locationQuery(lat, lng)..['limit'] = '$limit';
    final data = await _get('/services/$serviceId/providers', query: query);
    return _asList(data['providers']).map((e) => ProviderItem.fromJson(_asMap(e))).toList();
  }

  Map<String, String> _locationQuery(double? lat, double? lng) {
    if ((lat == null) != (lng == null)) {
      throw Exception('lat na lng lazima ziwe pamoja.');
    }
    if (lat == null || lng == null) return <String, String>{};
    return <String, String>{'lat': '$lat', 'lng': '$lng'};
  }

  Future<Map<String, dynamic>> _get(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse('$_base$path').replace(queryParameters: query == null || query.isEmpty ? null : query);
    late http.Response res;
    try {
      res = await _client.get(
        uri,
        headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
      ).timeout(const Duration(seconds: 20));
    } on TimeoutException {
      throw Exception('Request imechukua muda mrefu.');
    } on Object {
      throw Exception('Imeshindikana kuwasiliana na API.');
    }

    final body = _asMap(jsonDecode(res.body));
    if (res.statusCode >= 400 || body['success'] != true) {
      throw Exception(_s(body['message']).isEmpty ? 'API error (${res.statusCode})' : _s(body['message']));
    }
    return _asMap(body['data']);
  }

  void dispose() => _client.close();
}

class ServiceDetailsPage extends StatefulWidget {
  const ServiceDetailsPage({
    super.key,
    required this.serviceId,
    required this.apiBaseUrl,
    this.lat,
    this.lng,
    this.providersLimit = 20,
    this.onBookNow,
    this.onShowAllProviders,
  }) : assert((lat == null && lng == null) || (lat != null && lng != null));

  final int serviceId;
  final String apiBaseUrl;
  final double? lat;
  final double? lng;
  final int providersLimit;
  final void Function(ServiceDetails service, ProviderItem? selectedProvider)? onBookNow;
  final VoidCallback? onShowAllProviders;

  @override
  State<ServiceDetailsPage> createState() => _ServiceDetailsPageState();
}

class _ServiceDetailsPageState extends State<ServiceDetailsPage> with SingleTickerProviderStateMixin {
  late final CatalogApi _api;
  late final AnimationController _entry;
  late final PageController _pageController;

  Timer? _timer;
  bool _loading = true;
  String? _error;
  ServiceDetails? _service;
  List<ProviderItem> _providers = const [];
  int _pageIndex = 0;
  int? _selectedProviderId;

  @override
  void initState() {
    super.initState();
    _api = CatalogApi(baseUrl: widget.apiBaseUrl);
    _entry = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _pageController = PageController(viewportFraction: 0.93);
    _load();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pageController.dispose();
    _entry.dispose();
    _api.dispose();
    super.dispose();
  }

  List<String> get _images {
    final s = _service;
    if (s == null) return const [];
    final seen = <String>{};
    final out = <String>[];
    void add(String value) {
      final v = value.trim();
      if (v.isEmpty || seen.contains(v)) return;
      seen.add(v);
      out.add(v);
    }

    add(s.imageUrl);
    for (final g in s.gallery) {
      add(g);
    }
    return out;
  }

  ProviderItem? get _selectedProvider {
    final id = _selectedProviderId;
    if (id == null) return null;
    for (final p in _providers) {
      if (p.id == id) return p;
    }
    return null;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final result = await Future.wait<dynamic>([
        _api.fetchService(serviceId: widget.serviceId, lat: widget.lat, lng: widget.lng),
        _api.fetchProviders(
          serviceId: widget.serviceId,
          lat: widget.lat,
          lng: widget.lng,
          limit: widget.providersLimit,
        ),
      ]);
      if (!mounted) return;

      final providers = result[1] as List<ProviderItem>;
      setState(() {
        _service = result[0] as ServiceDetails;
        _providers = providers;
        _selectedProviderId = providers.isEmpty ? null : providers.first.id;
        _loading = false;
      });
      _entry.forward(from: 0);
      _startSlide();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = '$e';
      });
    }
  }

  void _startSlide() {
    _timer?.cancel();
    if (_images.length < 2) return;
    _timer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!_pageController.hasClients || _images.isEmpty) return;
      final next = (_pageIndex + 1) % _images.length;
      _pageController.animateToPage(next, duration: const Duration(milliseconds: 420), curve: Curves.easeOutCubic);
    });
  }

  Widget _reveal(int index, Widget child) {
    final start = (index * 0.1).clamp(0.0, 0.86);
    final anim = CurvedAnimation(parent: _entry, curve: Interval(start, 1, curve: Curves.easeOutCubic));
    return FadeTransition(
      opacity: anim,
      child: SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, 0.08), end: Offset.zero).animate(anim),
        child: child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final service = _service;

    return Scaffold(
      backgroundColor: GlamoPalette.bg,
      appBar: AppBar(
        title: const Text('Maelezo ya Huduma', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
        centerTitle: true,
        backgroundColor: GlamoPalette.bg,
        foregroundColor: GlamoPalette.text,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        scrolledUnderElevation: 0,
        actions: [IconButton(onPressed: () {}, icon: const Icon(Icons.share_outlined))],
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 240),
        child: _loading
            ? const _LoadingState()
            : _error != null
                ? _ErrorState(message: _error!, onRetry: _load)
                : service == null
                    ? _ErrorState(message: 'Huduma haijapatikana.', onRetry: _load)
                    : RefreshIndicator(
                        onRefresh: _load,
                        color: GlamoPalette.primary,
                        child: SingleChildScrollView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 130),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _reveal(
                                0,
                                _GalleryWidget(
                                  images: _images,
                                  controller: _pageController,
                                  pageIndex: _pageIndex,
                                  onPage: (v) => setState(() => _pageIndex = v),
                                ),
                              ),
                              const SizedBox(height: 14),
                              _reveal(1, _TopServiceInfo(service: service, providers: _providers)),
                              const SizedBox(height: 12),
                              _reveal(2, _PriceBreakdownWidget(price: service.price)),
                              if ((service.shortDesc ?? '').trim().isNotEmpty) ...[
                                const SizedBox(height: 12),
                                _reveal(3, _DescriptionWidget(text: service.shortDesc!)),
                              ],
                              const SizedBox(height: 14),
                              _reveal(
                                4,
                                _ProvidersWidget(
                                  providers: _providers,
                                  selectedProviderId: _selectedProviderId,
                                  onSelect: (id) => setState(() => _selectedProviderId = id),
                                  onShowAll: widget.onShowAllProviders,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
      ),
      bottomNavigationBar: service == null
          ? null
          : SafeArea(
              top: false,
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: GlamoPalette.border)),
                ),
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
                child: SizedBox(
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: () => widget.onBookNow?.call(service, _selectedProvider),
                    icon: const Icon(Icons.event_available_outlined, size: 20),
                    label: const Text('WEKA BOOKING', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: .3)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: GlamoPalette.primary,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
              ),
            ),
    );
  }
}

class _GalleryWidget extends StatelessWidget {
  const _GalleryWidget({
    required this.images,
    required this.controller,
    required this.pageIndex,
    required this.onPage,
  });

  final List<String> images;
  final PageController controller;
  final int pageIndex;
  final ValueChanged<int> onPage;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: AspectRatio(
            aspectRatio: 4 / 5,
            child: images.isEmpty
                ? _imgFallback()
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      PageView.builder(
                        controller: controller,
                        itemCount: images.length,
                        onPageChanged: onPage,
                        itemBuilder: (_, i) => Image.network(
                          images[i],
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => _imgFallback(),
                          loadingBuilder: (context, child, progress) => progress == null ? child : _imgFallback(),
                        ),
                      ),
                      const DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0x00000000), Color(0x55000000)],
                          ),
                        ),
                      ),
                    ],
                  ),
          ),
        ),
        if (images.length > 1) ...[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(images.length, (i) {
              final active = i == pageIndex;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: active ? 22 : 7,
                height: 7,
                decoration: BoxDecoration(
                  color: active ? GlamoPalette.primary : const Color(0xFFD8C7CD),
                  borderRadius: BorderRadius.circular(50),
                ),
              );
            }),
          ),
        ],
      ],
    );
  }

  Widget _imgFallback() => Container(
        color: const Color(0xFFF7ECEF),
        alignment: Alignment.center,
        child: const Icon(Icons.image_not_supported_outlined, color: GlamoPalette.primary, size: 42),
      );
}

class _TopServiceInfo extends StatelessWidget {
  const _TopServiceInfo({required this.service, required this.providers});

  final ServiceDetails service;
  final List<ProviderItem> providers;

  @override
  Widget build(BuildContext context) {
    final ratings = providers.map((e) => e.ratingAvg).whereType<double>().toList();
    final avg = ratings.isEmpty ? null : ratings.reduce((a, b) => a + b) / ratings.length;
    final providersTotal = service.providersTotal ?? providers.length;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: GlamoPalette.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  service.name,
                  style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w800, color: GlamoPalette.text),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (avg != null) _pill(Icons.star_rounded, avg.toStringAsFixed(1), GlamoPalette.primary),
                    _pill(Icons.person_pin_circle_outlined, '$providersTotal mtaalam', GlamoPalette.secondary),
                    if (service.categoryName.trim().isNotEmpty)
                      _pill(Icons.style_outlined, service.categoryName, GlamoPalette.primary),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                tzs(service.price.total),
                style: const TextStyle(fontSize: 23, color: GlamoPalette.primary, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 2),
              const Text('Bei ya kuanzia', style: TextStyle(fontSize: 12, color: GlamoPalette.muted)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _pill(IconData icon, String label, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: 0.24)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 4),
            Text(label, style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w700)),
          ],
        ),
      );
}

class _PriceBreakdownWidget extends StatelessWidget {
  const _PriceBreakdownWidget({required this.price});

  final ServicePrice price;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: GlamoPalette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.payments_outlined, color: GlamoPalette.primary, size: 20),
              SizedBox(width: 8),
              Text('Mchanganuo wa Bei', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            ],
          ),
          const SizedBox(height: 10),
          _priceRow('Huduma ya msingi', price.service),
          _priceRow('Vifaa', price.materials),
          _priceRow('Usage (${price.usagePercent.toStringAsFixed(0)}%)', price.usage),
          _priceRow(price.hasLocation ? 'Usafiri' : 'Usafiri (ongeza location)', price.hasLocation ? price.travel : 0),
          const Divider(color: GlamoPalette.border, height: 14),
          _priceRow('Jumla', price.total, total: true),
          if (price.hasLocation && price.distanceKm != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                'Umbali wa karibu: ${price.distanceKm!.toStringAsFixed(2)} km',
                style: const TextStyle(fontSize: 12, color: GlamoPalette.muted),
              ),
            ),
        ],
      ),
    );
  }

  Widget _priceRow(String label, double amount, {bool total = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  color: total ? GlamoPalette.text : GlamoPalette.muted,
                  fontWeight: total ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ),
            Text(
              tzs(amount),
              style: TextStyle(
                fontSize: total ? 15 : 13,
                color: total ? GlamoPalette.primary : GlamoPalette.text,
                fontWeight: total ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ],
        ),
      );
}

class _DescriptionWidget extends StatelessWidget {
  const _DescriptionWidget({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFAFB),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: GlamoPalette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Maelezo',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: GlamoPalette.text),
          ),
          const SizedBox(height: 8),
          Text(
            text,
            style: const TextStyle(
              fontSize: 13, // reduced as requested
              color: GlamoPalette.muted,
              height: 1.45,
            ),
          ),
        ],
      ),
    );
  }
}

class _ProvidersWidget extends StatelessWidget {
  const _ProvidersWidget({
    required this.providers,
    required this.selectedProviderId,
    required this.onSelect,
    this.onShowAll,
  });

  final List<ProviderItem> providers;
  final int? selectedProviderId;
  final ValueChanged<int> onSelect;
  final VoidCallback? onShowAll;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Watoa Huduma Karibu Nawe',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: GlamoPalette.text),
              ),
            ),
            if (onShowAll != null)
              TextButton(
                onPressed: onShowAll,
                style: TextButton.styleFrom(foregroundColor: GlamoPalette.primary),
                child: const Text('Ona wote', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
          ],
        ),
        const SizedBox(height: 8),
        if (providers.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: GlamoPalette.border),
            ),
            child: const Text(
              'Hakuna provider wa karibu kwa sasa.',
              style: TextStyle(fontSize: 13, color: GlamoPalette.muted),
            ),
          )
        else
          Column(
            children: providers.map((p) {
              final selected = p.id == selectedProviderId;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: selected ? const Color(0xFFFFF3F7) : Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: selected ? GlamoPalette.primary : GlamoPalette.border),
                ),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: SizedBox(
                        width: 54,
                        height: 54,
                        child: p.profileImageUrl.trim().isEmpty
                            ? _avatarFallback()
                            : Image.network(
                                p.profileImageUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => _avatarFallback(),
                              ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            p.displayName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 4),
                          Wrap(
                            spacing: 8,
                            runSpacing: 2,
                            children: [
                              if (p.ratingAvg != null)
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(Icons.star_rounded, size: 14, color: GlamoPalette.primary),
                                    const SizedBox(width: 2),
                                    Text(
                                      p.ratingAvg!.toStringAsFixed(1),
                                      style: const TextStyle(fontSize: 12, color: GlamoPalette.muted),
                                    ),
                                  ],
                                ),
                              if (p.distanceKm != null)
                                Text('${p.distanceKm!.toStringAsFixed(1)} km',
                                    style: const TextStyle(fontSize: 12, color: GlamoPalette.muted)),
                              Text(
                                tzs(p.totalPrice),
                                style: const TextStyle(fontSize: 12, color: GlamoPalette.primary, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    OutlinedButton(
                      onPressed: () => onSelect(p.id),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: selected ? Colors.white : GlamoPalette.primary,
                        backgroundColor: selected ? GlamoPalette.primary : Colors.transparent,
                        elevation: 0,
                        shadowColor: Colors.transparent,
                        side: BorderSide(color: selected ? GlamoPalette.primary : GlamoPalette.border),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      child: Text(selected ? 'Umechagua' : 'Chagua', style: const TextStyle(fontWeight: FontWeight.w700)),
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
      ],
    );
  }

  Widget _avatarFallback() => Container(
        color: const Color(0xFFF7ECEF),
        alignment: Alignment.center,
        child: const Icon(Icons.person_outline, color: GlamoPalette.primary),
      );
}

class _LoadingState extends StatefulWidget {
  const _LoadingState();

  @override
  State<_LoadingState> createState() => _LoadingStateState();
}

class _LoadingStateState extends State<_LoadingState> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat(reverse: true);
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: .35, end: 1).animate(_pulse),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: const [
          _SBox(320),
          SizedBox(height: 12),
          _SBox(110),
          SizedBox(height: 12),
          _SBox(210),
          SizedBox(height: 12),
          _SBox(90),
          SizedBox(height: 12),
          _SBox(90),
        ],
      ),
    );
  }
}

class _SBox extends StatelessWidget {
  const _SBox(this.h);
  final double h;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: h,
      decoration: BoxDecoration(
        color: const Color(0xFFF4ECEF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: GlamoPalette.border),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, color: GlamoPalette.primary, size: 34),
            const SizedBox(height: 10),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 13, color: GlamoPalette.muted, height: 1.45),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: onRetry,
              style: ElevatedButton.styleFrom(
                backgroundColor: GlamoPalette.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shadowColor: Colors.transparent,
              ),
              child: const Text('Jaribu tena'),
            ),
          ],
        ),
      ),
    );
  }
}
