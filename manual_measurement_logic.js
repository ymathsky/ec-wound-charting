// Filename: manual_measurement_logic.js

const ManualMeasurement = {
    canvas: null,
    pixelsPerCm: 0,
    currentState: '', // 'scaling', 'drawing'
    line: null,
    path: null,

    // DOM Elements
    modal: null,
    closeBtn: null,
    canvasWrapper: null,
    canvasEl: null,
    setScaleBtn: null,
    addHeadArrowBtn: null,
    drawAreaBtn: null,
    clearBtn: null,
    useMeasurementsBtn: null,
    instructionText: null,
    resLength: null,
    resWidth: null,
    resArea: null,

    // Dimensions for image loading
    imageWidth: 0,
    imageHeight: 0,

    // Initialize the tool with the uploaded photo file
    init: function(photoFile) {
        this.getDOMElements();
        this.resetState();
        this.loadPhotoToCanvas(photoFile);
        this.attachControlListeners();
    },

    getDOMElements: function() {
        this.modal = document.getElementById('manualMeasurementModal');
        this.closeBtn = document.getElementById('closeManualModalBtn');
        this.canvasWrapper = document.getElementById('canvas-wrapper');
        this.canvasEl = document.getElementById('measurementCanvas');
        this.setScaleBtn = document.getElementById('setScaleBtn');
        this.addHeadArrowBtn = document.getElementById('addManualHeadArrowBtn');
        this.drawAreaBtn = document.getElementById('drawAreaBtn');
        this.clearBtn = document.getElementById('clearBtn');
        this.useMeasurementsBtn = document.getElementById('useMeasurementsBtn');
        this.instructionText = document.getElementById('instruction-text');
        this.resLength = document.getElementById('resLength');
        this.resWidth = document.getElementById('resWidth');
        this.resArea = document.getElementById('resArea');

        // Initialize Fabric.js canvas only if not already done
        if (!this.canvas) {
            this.canvas = new fabric.Canvas('measurementCanvas', {
                isDrawingMode: false,
                selection: false,
                stopContextMenu: true,
            });
        }
    },

    resetState: function() {
        this.currentState = '';
        this.pixelsPerCm = 0;
        this.path = null;
        this.line = null;
        window.ManualMeasurementResults = {}; // Clear previous results

        if (this.canvas) {
            const bgImage = this.canvas.backgroundImage;
            this.canvas.clear();
            this.canvas.setBackgroundImage(bgImage, this.canvas.renderAll.bind(this.canvas));
            this.canvas.isDrawingMode = false;
        }

        if(this.setScaleBtn) this.setScaleBtn.disabled = false;
        if(this.drawAreaBtn) this.drawAreaBtn.disabled = true;
        if(this.useMeasurementsBtn) this.useMeasurementsBtn.disabled = true;
        if(this.instructionText) this.instructionText.textContent = "1. Click 'Set Scale' and draw a line on a ruler in the image.";
        if(this.resLength) this.resLength.textContent = 'N/A';
        if(this.resWidth) this.resWidth.textContent = 'N/A';
        if(this.resArea) this.resArea.textContent = 'N/A';
    },

    loadPhotoToCanvas: function(photoFile) {
        const reader = new FileReader();
        const self = this;

        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                self.imageWidth = img.width;
                self.imageHeight = img.height;

                // Set canvas size to fit wrapper responsively
                const wrapperRect = self.canvasWrapper.getBoundingClientRect();
                const containerWidth = wrapperRect.width * 0.95;
                const scale = containerWidth / img.width;

                const canvasHeight = img.height * scale;
                const canvasWidth = img.width * scale;

                self.canvas.setWidth(canvasWidth);
                self.canvas.setHeight(canvasHeight);

                self.canvas.setBackgroundImage(new fabric.Image(img, {
                    scaleX: scale,
                    scaleY: scale,
                    originX: 'left',
                    originY: 'top'
                }), self.canvas.renderAll.bind(self.canvas));

                // Once image is loaded, enable scale setting
                self.setScaleBtn.disabled = false;
            }
            img.src = e.target.result;
        }
        reader.readAsDataURL(photoFile);
    },

    attachControlListeners: function() {
        const self = this;

        // Clear button (resets drawing state, but keeps background image)
        this.clearBtn.onclick = () => this.resetState();

        // 1. Set Scale Mode
        this.setScaleBtn.onclick = function() {
            if (self.currentState === 'scaling') return;
            self.resetState();
            self.currentState = 'scaling';
            self.instructionText.textContent = "2. Draw a line over a known measurement (e.g., 1 cm on a ruler).";
            self.setScaleBtn.disabled = true;
            self.canvas.isDrawingMode = false;
        };

        // 1.5 Add Head Orientation Arrow
        if (this.addHeadArrowBtn) {
            this.addHeadArrowBtn.onclick = function() {
                // Create Arrow Group (Same as Annotation Tool)
                const triangle = new fabric.Triangle({
                    width: 20, height: 20, fill: 'blue', left: 0, top: -35, originX: 'center', originY: 'center'
                });

                const line = new fabric.Rect({
                    width: 6, height: 60, fill: 'blue', left: 0, top: 5, originX: 'center', originY: 'center'
                });

                const text = new fabric.Text('HEAD', {
                    fontSize: 16, fill: 'blue', left: 0, top: -55, originX: 'center', originY: 'center', fontWeight: 'bold'
                });

                const arrowGroup = new fabric.Group([line, triangle, text], {
                    left: self.canvas.width / 2,
                    top: self.canvas.height / 2,
                    angle: 0,
                    selectable: true,
                    hasControls: true,
                    hasBorders: true
                });

                self.canvas.add(arrowGroup);
                self.canvas.setActiveObject(arrowGroup);
                self.canvas.renderAll();
                
                // If we have an existing path, recalculate dimensions based on new orientation
                if (self.path) {
                    self.calculatePathDimensions(self.path);
                }
                
                // Listen for rotation/movement of the arrow to update calculations in real-time
                arrowGroup.on('modified', function() {
                    if (self.path) {
                        self.calculatePathDimensions(self.path);
                    }
                });
            };
        }

        // 2. Drawing Mode
        this.drawAreaBtn.onclick = function() {
            if (self.currentState === 'drawing') return;
            self.currentState = 'drawing';
            self.instructionText.textContent = "3. Draw the perimeter of the wound area.";
            self.canvas.isDrawingMode = true;
            self.canvas.freeDrawingBrush.width = 3;
            self.canvas.freeDrawingBrush.color = '#ff0000';
            self.useMeasurementsBtn.disabled = true;
        };

        // Canvas Mouse Down/Up/Move listeners
        this.canvas.on('mouse:down', function(o) {
            if (self.currentState === 'scaling') {
                const pointer = self.canvas.getPointer(o.e);
                self.line = new fabric.Line([pointer.x, pointer.y, pointer.x, pointer.y], {
                    stroke: '#0000ff',
                    strokeWidth: 2,
                    selectable: false,
                    evented: false,
                    padding: 0
                });
                self.canvas.add(self.line);
            }
        });

        this.canvas.on('mouse:move', function(o) {
            if (self.currentState === 'scaling' && self.line) {
                const pointer = self.canvas.getPointer(o.e);
                self.line.set({ x2: pointer.x, y2: pointer.y });
                self.canvas.renderAll();
            }
        });

        this.canvas.on('mouse:up', function(o) {
            if (self.currentState === 'scaling' && self.line) {
                // Scale line drawing complete. Ask user for cm value.
                const valStr = prompt("Enter the known length of the line in centimeters (cm):", "1");
                
                if (valStr === null) {
                    // User cancelled
                    self.canvas.remove(self.line);
                    self.line = null;
                    self.resetState();
                    return;
                }

                const valueCm = parseFloat(valStr);

                if (valueCm > 0) {
                    const lineLengthPixels = self.line.get('width');
                    self.pixelsPerCm = lineLengthPixels / valueCm;

                    self.instructionText.textContent = `Scale set: ${self.pixelsPerCm.toFixed(2)} pixels/cm. Now click 'Enable Free-hand Draw'.`;
                    self.line.set({ stroke: '#00cc00', strokeWidth: 4 });
                    self.line = null;
                    self.currentState = '';
                    self.drawAreaBtn.disabled = false;
                } else {
                    // Use custom alert if available, otherwise native
                    if (typeof showFloatingAlert === 'function') {
                        showFloatingAlert("Invalid centimeter value. Scale not set. Please try again.", 'error');
                    } else {
                        alert("Invalid centimeter value. Scale not set. Please try again.");
                    }
                    self.resetState();
                }
                self.canvas.renderAll();
            }
        });

        // Event for when free drawing path is added
        this.canvas.on('path:created', function(o) {
            if (self.currentState === 'drawing') {
                self.path = o.path;
                self.canvas.isDrawingMode = false;
                self.calculatePathDimensions(self.path);
                self.useMeasurementsBtn.disabled = false;
            }
        });
    },

    calculatePathDimensions: function(p) {
        if (!this.pixelsPerCm || !p) return;
        
        // Check for Head Orientation Arrow
        let headAngle = 0;
        const objects = this.canvas.getObjects();
        objects.forEach(obj => {
            if (obj.type === 'group') {
                const textObj = obj.getObjects().find(o => o.type === 'text' && o.text === 'HEAD');
                if (textObj) {
                    headAngle = obj.angle; 
                }
            }
        });

        // Clone the path to perform calculations without affecting the display
        // Fabric.js clone is async
        const self = this;
        p.clone(function(clonedPath) {
            // We want to measure the dimensions along the axis defined by headAngle.
            // To measure "Length" along the arrow's axis, we rotate the object by -headAngle.
            // This aligns the arrow's axis with the vertical Y-axis.
            // Then the bounding box Height is the Length.
            
            // Reset angle to 0 relative to the rotation we want to apply?
            // The cloned path has the same transform as the original.
            // We just want to apply an ADDITIONAL rotation of -headAngle?
            // No, we want the absolute rotation to be (original_rotation - headAngle).
            // But free drawing paths usually have angle 0.
            
            // If we rotate the object by -headAngle, we are effectively rotating the coordinate system by +headAngle.
            // If the arrow is pointing Right (90 deg), we rotate by -90.
            // A vertical line (Length) would become horizontal.
            // Wait.
            // Head Arrow = "Up" vector for the wound.
            // If Head Arrow is at 90 deg (pointing Right).
            // Then "Length" is the dimension along the X axis.
            // If we rotate the object by -90 deg, the X axis becomes the Y axis (Up).
            // So the dimension that was along X (Length) is now along Y (Height).
            // So bb.height will be the Length. Correct.
            
            // Ensure we rotate around the center
            clonedPath.rotate(clonedPath.angle - headAngle);
            clonedPath.setCoords();
            
            const bb = clonedPath.getBoundingRect();

            // Measure bounding box for L and W
            // Height corresponds to the dimension parallel to the arrow (Head-to-Toe Length)
            // Width corresponds to the dimension perpendicular to the arrow (Width)
            const lengthCm = (bb.height / self.pixelsPerCm);
            const widthCm = (bb.width / self.pixelsPerCm);

            // Use a placeholder formula for area since accurate area requires complex polygon analysis
            // For simplicity, we use the bounding box area (L x W)
            const areaCm2 = lengthCm * widthCm;

            // Update DOM results
            if (self.resLength) self.resLength.textContent = `${lengthCm.toFixed(2)} cm`;
            if (self.resWidth) self.resWidth.textContent = `${widthCm.toFixed(2)} cm`;
            if (self.resArea) self.resArea.textContent = `${areaCm2.toFixed(2)} cm²`;

            // Save results to the global variable for wound_assessment_logic.js to consume
            window.ManualMeasurementResults = {
                length: lengthCm,
                width: widthCm,
                area: areaCm2
            };

            // Final instruction
            if (self.instructionText) self.instructionText.textContent = "Measurement complete. Click 'Copy to Assessment'.";
        });
    }
};

// Expose the object globally
window.ManualMeasurement = ManualMeasurement;