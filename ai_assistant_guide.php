<?php
require_once 'templates/header.php';

// Capture parameters to preserve navigation context
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Build the back link
$back_link = "visit_ai_assistant.php";
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
                    <i data-lucide="bot" class="w-8 h-8 mr-3 text-indigo-600"></i>
                    AI Assistant User Guide
                </h1>
                <a href="<?php echo $back_link; ?>" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Assistant
                </a>
            </div>
        </header>

        <!-- Scrollable Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-5xl mx-auto space-y-12">

                <!-- Hero Section -->
                <section class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-lg text-white overflow-hidden relative">
                    <div class="absolute inset-0 bg-white opacity-10 pattern-dots"></div>
                    <div class="relative p-10 md:p-14 text-center">
                        <h2 class="text-3xl md:text-4xl font-extrabold mb-4">Your Intelligent Clinical Partner</h2>
                        <p class="text-lg md:text-xl text-indigo-100 max-w-3xl mx-auto">
                            Move beyond simple dictation. Engage in a real-time dialogue with your EMR. 
                            The AI Assistant drafts your notes, answers clinical questions, and provides 
                            decision support as you work, all in a unified split-screen interface.
                        </p>
                    </div>
                </section>

                <!-- Why It's Powerful -->
                <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Collaboration Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-6">
                            <i data-lucide="message-square" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Interactive Collaboration</h3>
                        <ul class="space-y-4 text-gray-600">
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-purple-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Conversational Charting:</strong> Don't just dictate; talk to the AI. Say "The wound looks better," and it updates the objective section accordingly.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-purple-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Instant Information:</strong> Ask "What was the last A1c?" or "Summarize previous wound measurements" without leaving the screen.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-purple-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Quick Actions:</strong> Use one-click chips to "Suggest Plan," "Review Vitals," or "Draft Referral" instantly.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Live Drafting Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 hover:shadow-md transition-shadow">
                        <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-6">
                            <i data-lucide="file-edit" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Real-Time Drafting</h3>
                        <ul class="space-y-4 text-gray-600">
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Live Preview:</strong> Watch the SOAP note build itself in the right-hand panel as you interact with the assistant.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>AI Insights:</strong> The system proactively suggests clinical improvements, missing data points, or potential coding opportunities.</span>
                            </li>
                            <li class="flex items-start">
                                <i data-lucide="check-circle" class="w-5 h-5 text-blue-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span><strong>Seamless Editing:</strong> The draft is fully editable. You can manually tweak the text while the AI continues to append new information.</span>
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
                                The Assistant uses advanced Large Language Models (LLMs) via <strong>Google Vertex AI</strong> to understand context, medical terminology, and clinical reasoning.
                            </p>
                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-gray-300">
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Context Window > 1M Tokens</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Multi-Turn Conversation Memory</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Real-Time Latency Optimization</li>
                                <li class="flex items-center"><i data-lucide="check" class="w-4 h-4 text-green-400 mr-2"></i> Secure & Private (HIPAA Compliant)</li>
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
                                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Mode</div>
                                    <div class="font-mono font-bold text-green-400">Interactive</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Step-by-Step Guide -->
                <div class="border-t border-gray-200 pt-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Workflow Overview</h2>
                    
                    <!-- Step 1: Chat -->
                    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center">
                            <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 font-bold">1</span>
                            <h3 class="text-xl font-bold text-gray-800">The Chat Interface (Left Panel)</h3>
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <p class="text-gray-600 mb-4 text-lg">This is your command center. Use the microphone or text input to communicate with the AI. You can provide clinical updates, ask for summaries, or direct the AI to modify the note.</p>
                                    <div class="bg-indigo-50 rounded-lg p-5 border border-indigo-100">
                                        <h4 class="font-bold text-indigo-900 mb-2 flex items-center"><i data-lucide="message-circle" class="w-4 h-4 mr-2"></i> Example Prompts</h4>
                                        <ul class="list-disc list-inside space-y-2 text-indigo-800 text-sm">
                                            <li>"The patient reports pain is 4/10 today, improved from last week."</li>
                                            <li>"Add a diagnosis of Venous Stasis Ulcer."</li>
                                            <li>"What medications is the patient currently taking?"</li>
                                        </ul>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-800 mb-3">Quick Actions</h4>
                                    <p class="text-gray-600 mb-4">Use the chips above the input bar for common tasks:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs border border-gray-200">Summarize History</span>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs border border-gray-200">Suggest Plan</span>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs border border-gray-200">Review Vitals</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Step 2: Live Note -->
                    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center">
                            <span class="bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 font-bold">2</span>
                            <h3 class="text-xl font-bold text-gray-800">Live Note Draft (Right Panel)</h3>
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="flex flex-col md:flex-row gap-8 items-center">
                                <div class="flex-1">
                                    <p class="text-gray-600 mb-4 text-lg">As you speak, the AI automatically structures your input into a professional SOAP note format. This draft updates in real-time.</p>
                                    <ul class="space-y-3 text-gray-700">
                                        <li class="flex items-center"><i data-lucide="edit-3" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>Editable:</strong> Click anywhere in the note to make manual corrections.</li>
                                        <li class="flex items-center"><i data-lucide="save" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>Auto-Save:</strong> Your progress is saved automatically every few seconds.</li>
                                        <li class="flex items-center"><i data-lucide="sparkles" class="w-5 h-5 text-indigo-500 mr-3"></i> <strong>AI Insights:</strong> Look for the "AI Insights" box at the top for clinical suggestions you can approve with one click.</li>
                                    </ul>
                                </div>
                                <div class="flex-1 bg-gray-50 rounded-xl p-6 border border-gray-200 font-mono text-xs text-gray-600">
                                    <div class="mb-2 font-bold text-gray-800">SUBJECTIVE:</div>
                                    <div class="mb-4">Patient presents for follow-up of LLE ulcer. Reports pain has decreased...</div>
                                    <div class="mb-2 font-bold text-gray-800">OBJECTIVE:</div>
                                    <div class="mb-4">Wound measures 2.5 x 3.0 cm. Granulation tissue 80%...</div>
                                    <div class="mb-2 font-bold text-gray-800">ASSESSMENT:</div>
                                    <div>Venous stasis ulcer, improving.</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sample Interaction -->
                <section class="border-t border-gray-200 pt-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Sample Interaction Script</h2>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-800">Try This Workflow</h3>
                            <span class="text-sm text-gray-500 bg-white px-2 py-1 rounded border border-gray-200">Demo Script</span>
                        </div>
                        <div class="p-6 md:p-8 space-y-6">
                            <div class="space-y-4">
                                <!-- Turn 1 -->
                                <div class="flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0"><i data-lucide="user" class="w-4 h-4 text-gray-600"></i></div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-500 mb-1">You (Voice or Text)</p>
                                        <div class="bg-gray-100 rounded-lg rounded-tl-none p-3 text-gray-800">
                                            "Start a new note. The patient is here for a follow-up on their right heel ulcer. They say it's less painful today."
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-4 flex-row-reverse">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0"><i data-lucide="bot" class="w-4 h-4 text-indigo-600"></i></div>
                                    <div class="flex-1 text-right">
                                        <p class="text-sm text-gray-500 mb-1">AI Assistant</p>
                                        <div class="bg-indigo-50 rounded-lg rounded-tr-none p-3 text-indigo-900 inline-block text-left">
                                            I've started the note. I added the chief complaint and the subjective report of decreased pain.
                                        </div>
                                    </div>
                                </div>

                                <!-- Turn 2 -->
                                <div class="flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0"><i data-lucide="user" class="w-4 h-4 text-gray-600"></i></div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-500 mb-1">You</p>
                                        <div class="bg-gray-100 rounded-lg rounded-tl-none p-3 text-gray-800">
                                            "On exam, the wound measures 2 by 2 centimeters with 100% granulation tissue. No drainage. Periwound is healthy."
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-4 flex-row-reverse">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0"><i data-lucide="bot" class="w-4 h-4 text-indigo-600"></i></div>
                                    <div class="flex-1 text-right">
                                        <p class="text-sm text-gray-500 mb-1">AI Assistant</p>
                                        <div class="bg-indigo-50 rounded-lg rounded-tr-none p-3 text-indigo-900 inline-block text-left">
                                            Updated the Objective section with those measurements. It sounds like the wound is progressing well. Should I suggest a plan to continue the current treatment?
                                        </div>
                                    </div>
                                </div>

                                <!-- Turn 3 -->
                                <div class="flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0"><i data-lucide="user" class="w-4 h-4 text-gray-600"></i></div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-500 mb-1">You</p>
                                        <div class="bg-gray-100 rounded-lg rounded-tl-none p-3 text-gray-800">
                                            "Yes, continue the foam dressing changed every 3 days. Also, schedule a follow-up in 2 weeks."
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
