<?php
require_once 'templates/header.php';

// Capture parameters to preserve navigation context
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Build the back link
$back_link = "visit_narrative.php";
if ($patient_id > 0 && $appointment_id > 0) {
    $back_link .= "?patient_id=$patient_id&appointment_id=$appointment_id&user_id=$user_id";
}
?>

<div class="flex h-screen bg-gray-50">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64">
            <?php require_once 'templates/sidebar.php'; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Header -->
        <header class="bg-white shadow-sm z-10">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i data-lucide="mic" class="w-8 h-8 mr-3 text-indigo-600"></i>
                    Dictation Mode User Guide
                </h1>
                <a href="<?php echo $back_link; ?>" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Dictation
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-5xl mx-auto space-y-12">

                <!-- Hero Section -->
                <section class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg text-white overflow-hidden relative">
                    <div class="absolute inset-0 bg-white opacity-10 pattern-dots"></div>
                    <div class="relative p-10 md:p-14 text-center">
                        <h2 class="text-3xl md:text-4xl font-extrabold mb-4">Transform Your Documentation Workflow</h2>
                        <p class="text-lg md:text-xl text-indigo-100 max-w-3xl mx-auto">
                            Experience the future of charting. Turn 15 minutes of typing into 3 minutes of speaking. 
                            Our AI-powered Dictation Mode combines voice recognition, image analysis, and automated coding 
                            to deliver accurate, compliant charts in record time.
                        </p>
                    </div>
                </section>

                <!-- Why It's Efficient & Reliable -->
                <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Efficiency Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-6">
                            <i data-lucide="zap" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Unmatched Efficiency</h3>
                        <ul class="space-y-4 text-gray-600">
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>3x Faster Charting:</strong> Speak at 150 words per minute versus typing at 40. Finish your charts before the patient leaves the room.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Smart Macros:</strong> Use single phrases like "Insert Normal Skin" to populate entire sections instantly, ensuring consistency across visits.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Automated Coding:</strong> The AI suggests ICD-10 and CPT codes automatically, reducing administrative burden and documentation gaps.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Reliability Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-6">
                            <i data-lucide="shield-check" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Clinical Reliability</h3>
                        <ul class="space-y-4 text-gray-600">
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Context-Aware AI:</strong> Our model understands wound care terminology, differentiating between "slough" and "eschar" with high accuracy.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Visual Evidence:</strong> Annotated photos are permanently linked to the visit, providing indisputable proof of progression for audits.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Human-in-the-Loop:</strong> You always have the final say. Review and edit every AI suggestion before it becomes part of the permanent record.</span>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Technology Stack -->
                <section class="bg-gray-900 rounded-xl shadow-lg text-white p-8 overflow-hidden relative">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-indigo-500 rounded-full opacity-20 blur-xl"></div>
                    <div class="absolute bottom-0 left-0 -mb-4 -ml-4 w-32 h-32 bg-purple-500 rounded-full opacity-20 blur-xl"></div>
                    
                    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold mb-3 flex items-center">
                                <i data-lucide="cpu" class="w-6 h-6 mr-3 text-indigo-400"></i>
                                Powered by Google Vertex AI
                            </h3>
                            <p class="text-gray-300 mb-4">
                                We leverage the cutting-edge capabilities of <strong>Google Vertex AI</strong> to drive our clinical intelligence engine. This enterprise-grade platform ensures:
                            </p>
                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-gray-300">
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> HIPAA-Compliant Data Processing</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Multimodal Analysis (Text + Images)</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> 99.9% Uptime & Reliability</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Continuous Model Learning</li>
                            </ul>
                        </div>
                        <div class="flex-shrink-0 bg-white/10 backdrop-blur-sm p-4 rounded-lg border border-white/10">
                            <div class="flex items-center space-x-4">
                                <div class="text-center">
                                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Model</div>
                                    <div class="font-mono font-bold text-indigo-300">Gemini 1.5 Pro</div>
                                </div>
                                <div class="h-8 w-px bg-white/20"></div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Latency</div>
                                    <div class="font-mono font-bold text-green-400">< 2.5s</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Step-by-Step Guide -->
                <div class="border-t border-gray-200 pt-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">How It Works</h2>
                    
                    <!-- Step 1: Dictation -->
                    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center">
                            <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 font-bold">1</span>
                            <h3 class="text-xl font-bold text-gray-800">Voice Dictation</h3>
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <p class="text-gray-600 mb-4 text-lg">Activate the secure medical dictation engine and speak naturally. The system captures your HPI, ROS, and Physical Exam findings, converting your narrative into structured clinical data.</p>
                                    <div class="bg-indigo-50 rounded-lg p-5 border border-indigo-100">
                                        <h4 class="font-bold text-indigo-900 mb-2 flex items-center"><i data-lucide="mic" class="w-4 h-4 mr-2"></i> Pro Tips for Best Results</h4>
                                        <ul class="list-disc list-inside space-y-2 text-indigo-800 text-sm">
                                            <li>Speak clearly and at a normal pace, as if presenting to a colleague.</li>
                                            <li>Use punctuation commands like "Period", "New Paragraph" to structure your note.</li>
                                            <li>State measurements clearly: "Length 2.5 cm, Width 1.2 cm".</li>
                                        </ul>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-800 mb-3">Command Reference</h4>
                                    <div class="overflow-hidden rounded-lg border border-gray-200">
                                        <table class="w-full text-sm text-left">
                                            <thead class="bg-gray-100 text-gray-700">
                                                <tr>
                                                    <th class="p-3">Command</th>
                                                    <th class="p-3">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <tr>
                                                    <td class="p-3 font-medium text-indigo-600">"Insert Normal Skin"</td>
                                                    <td class="p-3">Populates standard skin exam findings.</td>
                                                </tr>
                                                <tr>
                                                    <td class="p-3 font-medium text-indigo-600">"Insert Wound Care Plan"</td>
                                                    <td class="p-3">Adds standard cleansing/dressing protocol.</td>
                                                </tr>
                                                <tr>
                                                    <td class="p-3 font-medium text-green-600">"EC Process Note"</td>
                                                    <td class="p-3">Triggers AI processing immediately.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Step 2: Photos -->
                    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center">
                            <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 font-bold">2</span>
                            <h3 class="text-xl font-bold text-gray-800">Visual Documentation</h3>
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="flex flex-col md:flex-row gap-8 items-center">
                                <div class="flex-1">
                                    <p class="text-gray-600 mb-4 text-lg">Capture objective clinical evidence. Our integrated photo tool allows you to track wound progression longitudinally and annotate specific areas of concern.</p>
                                    <ul class="space-y-3 text-gray-700">
                                        <li class="flex items-center"><i data-lucide="camera" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>Capture:</strong> Upload high-resolution images directly from your device.</li>
                                        <li class="flex items-center"><i data-lucide="tag" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>Categorize:</strong> Label as "Pre-Debridement" or "Post-Debridement" to support procedural documentation.</li>
                                        <li class="flex items-center"><i data-lucide="pen-tool" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>Annotate:</strong> Draw directly on the image to highlight undermining, tunneling, or tissue viability.</li>
                                    </ul>
                                </div>
                                <div class="flex-1 bg-gray-100 rounded-xl p-8 flex items-center justify-center border border-gray-200">
                                    <div class="text-center">
                                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white shadow-sm mb-4">
                                            <i data-lucide="image" class="w-8 h-8 text-indigo-600"></i>
                                        </div>
                                        <p class="text-sm text-gray-500 font-medium">Secure, Encrypted Image Storage</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Step 3: AI Processing -->
                    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center">
                            <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 font-bold">3</span>
                            <h3 class="text-xl font-bold text-gray-800">Intelligent Synthesis</h3>
                        </div>
                        <div class="p-6 md:p-8">
                            <p class="text-gray-600 mb-6 text-lg">Click <strong>Process with AI</strong> to initiate Automated Clinical Abstraction. The system synthesizes your voice narrative and visual data into a compliant, medical-grade record.</p>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:border-indigo-300 transition-colors">
                                    <div class="text-indigo-600 mb-2"><i data-lucide="file-text" class="w-6 h-6"></i></div>
                                    <h4 class="font-bold text-gray-900">SOAP Note</h4>
                                    <p class="text-sm text-gray-500 mt-1">Standardized Subjective, Objective, Assessment, and Plan.</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:border-indigo-300 transition-colors">
                                    <div class="text-indigo-600 mb-2"><i data-lucide="activity" class="w-6 h-6"></i></div>
                                    <h4 class="font-bold text-gray-900">ICD-10 Codes</h4>
                                    <p class="text-sm text-gray-500 mt-1">Specific diagnosis codes mapped from your narrative.</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:border-indigo-300 transition-colors">
                                    <div class="text-indigo-600 mb-2"><i data-lucide="ruler" class="w-6 h-6"></i></div>
                                    <h4 class="font-bold text-gray-900">Measurements</h4>
                                    <p class="text-sm text-gray-500 mt-1">Precise dimensions & depth extracted for tracking.</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:border-indigo-300 transition-colors">
                                    <div class="text-indigo-600 mb-2"><i data-lucide="scissors" class="w-6 h-6"></i></div>
                                    <h4 class="font-bold text-gray-900">Debridement</h4>
                                    <p class="text-sm text-gray-500 mt-1">Procedure notes & depth tracking.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sample Documentation -->
                <section class="border-t border-gray-200 pt-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Sample Clinical Documentation</h2>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-800">Complete Narrative Example</h3>
                            <span class="text-sm text-gray-500 bg-white px-2 py-1 rounded border border-gray-200">Read Aloud to Test</span>
                        </div>
                        <div class="p-6 md:p-8 space-y-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div>
                                    <h4 class="font-bold text-indigo-900 mb-3 flex items-center"><i data-lucide="mic" class="w-4 h-4 mr-2"></i> Dictation Script</h4>
                                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 text-gray-700 font-mono text-sm leading-relaxed h-full">
                                        <p class="mb-4"><strong class="text-gray-900">History of Present Illness:</strong><br>
                                        This is a 65-year-old male with a history of Type 2 Diabetes Mellitus, presenting for follow-up of a chronic ulcer on the left plantar foot. The patient reports the wound has been present for approximately 3 months. He notes mild drainage but denies fever, chills, or increasing redness.</p>

                                        <p class="mb-4"><strong class="text-gray-900">Objective:</strong><br>
                                        Examination of the left foot reveals a full-thickness ulcer on the plantar aspect of the first metatarsal head, measuring 2.5 cm by 2.0 cm with a depth of 0.5 cm. The wound bed consists of 60% red granulation tissue and 40% yellow fibrin slough. There is no probing to bone. Periwound skin is macerated.</p>

                                        <p class="mb-4"><strong class="text-gray-900">Debridement Procedure:</strong><br>
                                        Informed consent was obtained. The left foot ulcer was cleansed with normal saline. Using a #15 scalpel and curette, sharp debridement was performed to remove devitalized tissue, including hyperkeratotic callus and fibrin slough. Post-debridement, the wound bed appeared clean with healthy bleeding edges.</p>

                                        <p><strong class="text-gray-900">Assessment & Plan:</strong><br>
                                        1. Diabetic Foot Ulcer of the left foot, stable.<br>
                                        2. Type 2 Diabetes Mellitus with diabetic polyneuropathy.<br>
                                        Plan: Continue Metformin 500mg twice daily. Apply silver alginate dressing. Follow up in 1 week.</p>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-green-800 mb-3 flex items-center"><i data-lucide="sparkles" class="w-4 h-4 mr-2"></i> AI Output (Assessment)</h4>
                                    <div class="bg-green-50 p-5 rounded-lg border border-green-100 text-gray-800 text-sm leading-relaxed">
                                        <div class="mb-4">
                                            <span class="text-xs font-bold uppercase text-green-600 tracking-wider">Generated Diagnosis</span>
                                            <div class="font-medium mt-1">L97.512 - Non-pressure chronic ulcer of other part of left foot with fat layer exposed</div>
                                            <div class="font-medium mt-1">E11.40 - Type 2 diabetes mellitus with diabetic neuropathy, unspecified</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <span class="text-xs font-bold uppercase text-green-600 tracking-wider">Generated Assessment</span>
                                            <p class="mt-1 italic">"Patient presents with a chronic diabetic foot ulcer on the left plantar aspect (2.5x2.0x0.5cm). Wound bed shows 60% granulation / 40% slough. Status post excisional debridement of devitalized tissue. No signs of acute infection. Periwound maceration noted."</p>
                                        </div>

                                        <div>
                                            <span class="text-xs font-bold uppercase text-green-600 tracking-wider">Generated Plan</span>
                                            <ul class="list-disc list-inside mt-1 space-y-1">
                                                <li>Continue current glycemic control (Metformin).</li>
                                                <li>Local wound care: Silver alginate dressing daily.</li>
                                                <li>Offloading: Surgical shoe.</li>
                                                <li>Follow-up: 1 week for re-evaluation.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();
</script>

<?php require_once 'templates/footer.php'; ?>
