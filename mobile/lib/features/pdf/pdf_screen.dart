import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../core/api/api_exception.dart';

/// Document à afficher (reçu, bulletin) : chargé à l'ouverture.
class PdfRequest {
  const PdfRequest({required this.title, required this.fileName, required this.load});

  final String title;
  final String fileName;
  final Future<Uint8List> Function() load;
}

/// Aperçu d'un PDF avec partage (WhatsApp…) et impression.
class PdfScreen extends StatefulWidget {
  const PdfScreen({super.key, required this.request});

  final PdfRequest request;

  @override
  State<PdfScreen> createState() => _PdfScreenState();
}

class _PdfScreenState extends State<PdfScreen> {
  late final Future<Uint8List> _document = widget.request.load();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.request.title)),
      body: FutureBuilder<Uint8List>(
        future: _document,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(
              child: Padding(padding: const EdgeInsets.all(24), child: Text(ApiException.from(snapshot.error!).message)),
            );
          }
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }

          return PdfPreview(
            build: (_) async => snapshot.data!,
            pdfFileName: widget.request.fileName,
            canChangeOrientation: false,
            canChangePageFormat: false,
            canDebug: false,
          );
        },
      ),
    );
  }
}
